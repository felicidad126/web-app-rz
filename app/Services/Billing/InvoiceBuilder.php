<?php

namespace App\Services\Billing;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\PackageUsage;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\User;
use App\Models\Visit;
use App\Services\NumberSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membuat, menghitung ulang, dan menerbitkan tagihan.
 *
 * Tagihan kunjungan diisi otomatis dari tindakan (tarif snapshot) dan resep (harga jual obat).
 * Tindakan yang tercakup paket aktif pasien bertarif 0 dan memakai kuota paket saat tagihan diterbitkan.
 */
final class InvoiceBuilder
{
    /** Tagihan untuk kunjungan: yang sudah ada (bukan void) atau draf baru. */
    public static function forVisit(Visit $visit): Invoice
    {
        if ($existing = $visit->activeInvoice()) {
            return $existing;
        }

        if (! in_array($visit->status, [VisitStatus::InTreatment, VisitStatus::Done], true)) {
            throw new InvalidArgumentException('Tagihan dibuat setelah pasien diperiksa (status kunjungan: '.$visit->status->getLabel().').');
        }

        return DB::transaction(function () use ($visit): Invoice {
            $visit->loadMissing('patient');

            $invoice = Invoice::create([
                'branch_id' => $visit->branch_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'payer_id' => $visit->payer_id ?? $visit->patient->default_payer_id,
                'status' => InvoiceStatus::Draft,
            ]);

            self::addVisitLines($invoice, $visit);
            self::recalculate($invoice);

            return $invoice;
        });
    }

    /** Draf tanpa kunjungan (penjualan langsung, mis. sikat gigi atau obat). */
    public static function blank(Patient $patient, int $branchId): Invoice
    {
        return Invoice::create([
            'branch_id' => $branchId,
            'patient_id' => $patient->id,
            'payer_id' => $patient->default_payer_id,
            'status' => InvoiceStatus::Draft,
        ]);
    }

    /** Ambil ulang tindakan & resep dari kunjungan (baris tambahan manual tetap). */
    public static function refreshFromVisit(Invoice $invoice): void
    {
        self::ensureDraft($invoice);

        if ($invoice->visit_id === null) {
            return;
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->items()
                ->where(fn ($q) => $q->whereNotNull('visit_procedure_id')->orWhereNotNull('prescription_item_id'))
                ->get()
                ->each->delete();

            /** @var Visit $visit */
            $visit = Visit::findOrFail($invoice->visit_id);
            self::addVisitLines($invoice, $visit);
            self::recalculate($invoice);
        });
    }

    /** Hitung ulang semua angka draf dari barisnya. */
    public static function recalculate(Invoice $invoice): void
    {
        self::ensureDraft($invoice);

        $items = $invoice->items()->get();
        $result = InvoiceCalculator::calculate(
            $items->map(fn (InvoiceItem $item): array => [
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'item_discount' => $item->item_discount,
                'item_discount_percent' => $item->item_discount_percent,
                'tax_rate' => $item->tax_rate,
            ])->values()->all(),
            $invoice->invoice_discount,
            $invoice->invoice_discount_percent,
            (int) config('clinic.billing.rounding', 0),
            $invoice->payer_portion,
        );

        foreach ($items->values() as $index => $item) {
            $line = $result['lines'][$index];
            $item->fill([
                'item_discount' => $line['item_discount'],
                'allocated_invoice_discount' => $line['allocated_invoice_discount'],
                'net_amount' => $line['net_amount'],
                'tax' => $line['tax'],
            ]);

            if ($item->isDirty()) {
                $item->save();
            }
        }

        $invoice->fill([
            'subtotal' => $result['subtotal'],
            'invoice_discount' => $result['invoice_discount'],
            'tax_total' => $result['tax_total'],
            'rounding' => $result['rounding'],
            'total' => $result['total'],
            'payer_portion' => $result['payer_portion'],
            'patient_portion' => $result['patient_portion'],
        ])->save();
    }

    /**
     * Terbitkan draf: beri nomor, kunci baris, catat pemakaian kuota paket, tandai kunjungan "sudah ditagih".
     */
    public static function issue(Invoice $invoice, ?User $user = null): Invoice
    {
        self::ensureDraft($invoice);

        return DB::transaction(function () use ($invoice, $user): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            self::ensureDraft($invoice);
            self::recalculate($invoice);

            $items = $invoice->items()->get();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('Tagihan belum punya baris.');
            }

            self::recordPackageUsages($invoice, $items->whereNotNull('patient_package_id')->where('item_type', InvoiceItemType::Procedure)->all());

            $invoice->loadMissing('branch');
            $invoice->forceFill([
                'invoice_number' => NumberSequence::nextInvoiceNumber($invoice->branch->code, now()),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'issued_by' => ($user !== null ? $user->id : auth()->id()),
            ])->save();

            foreach ($items->whereNotNull('installment_id') as $item) {
                Installment::whereKey($item->installment_id)->update(['invoice_id' => $invoice->id, 'status' => InstallmentStatus::Billed]);
            }

            if ($invoice->visit_id !== null) {
                Visit::whereKey($invoice->visit_id)->update(['status' => VisitStatus::Billed]);
            }

            $invoice->syncPaymentStatus();

            return $invoice;
        });
    }

    /** Tagihan untuk satu cicilan paket (langsung diterbitkan). */
    public static function forInstallment(Installment $installment, ?User $user = null): Invoice
    {
        $installment->loadMissing('patientPackage', 'invoice');

        if ($installment->invoice !== null && $installment->invoice->status !== InvoiceStatus::Void) {
            return $installment->invoice;
        }

        if (in_array($installment->status, [InstallmentStatus::Paid, InstallmentStatus::Cancelled], true)) {
            throw new InvalidArgumentException($installment->label().' sudah '.$installment->status->getLabel().'.');
        }

        $package = $installment->patientPackage;

        return DB::transaction(function () use ($installment, $package, $user): Invoice {
            $invoice = Invoice::create([
                'branch_id' => $package->branch_id,
                'patient_id' => $package->patient_id,
                'status' => InvoiceStatus::Draft,
            ]);

            $invoice->items()->create([
                'item_type' => InvoiceItemType::Installment,
                'patient_package_id' => $package->id,
                'installment_id' => $installment->id,
                'description' => $package->name.' · '.$installment->label(),
                'qty' => 1,
                'unit_price' => $installment->amount,
                'practitioner_id' => $package->practitioner_id,
            ]);

            return self::issue($invoice, $user);
        });
    }

    /** Batalkan draf (belum ada nomor & uang, jadi tidak perlu persetujuan). */
    public static function cancelDraft(Invoice $invoice, string $reason, ?User $user = null): void
    {
        self::ensureDraft($invoice);

        $invoice->forceFill([
            'status' => InvoiceStatus::Void,
            'void_reason' => $reason,
            'voided_by' => ($user !== null ? $user->id : auth()->id()),
            'voided_at' => now(),
        ])->save();
    }

    /**
     * Harga satuan obat/bahan dalam satuan yang dipilih (harga jual per satuan dasar × faktor konversi).
     */
    public static function itemUnitPrice(Item $item, ?int $unitId): int
    {
        if ($item->sell_price === null) {
            return 0;
        }

        return (int) round($item->toBaseQuantity(1, $unitId ?? $item->base_unit_id) * $item->sell_price);
    }

    private static function addVisitLines(Invoice $invoice, Visit $visit): void
    {
        $visit->load([
            'procedures' => fn ($q) => $q->orderBy('id'),
            'procedures.treatment',
            'prescription.items.item',
            'prescription.items.unit',
        ]);

        $sort = (int) $invoice->items()->max('sort');
        $quota = self::availableQuota($visit->patient_id);

        foreach ($visit->procedures as $procedure) {
            $package = self::takeQuota($quota, $procedure->treatment_id, $procedure->qty);

            $invoice->items()->create([
                'item_type' => InvoiceItemType::Procedure,
                'visit_procedure_id' => $procedure->id,
                'treatment_id' => $procedure->treatment_id,
                'patient_package_id' => $package?->id,
                'description' => $procedure->treatment->name.($package ? ' · paket '.$package->package_number : ''),
                'tooth_number' => $procedure->tooth_number,
                'surfaces' => $procedure->surfaces,
                'qty' => $procedure->qty,
                'unit_price' => $package ? 0 : $procedure->price_snapshot,
                'practitioner_id' => $procedure->operator_id,
                'assistant_id' => $procedure->assistant_id,
                'sort' => ++$sort,
            ]);
        }

        foreach ($visit->prescription->items ?? [] as $line) {
            if ($line->item->sell_price === null) {
                continue;
            }

            $invoice->items()->create([
                'item_type' => InvoiceItemType::Item,
                'item_id' => $line->item_id,
                'unit_id' => $line->unit_id,
                'prescription_item_id' => $line->id,
                'description' => $line->item->name.' · resep',
                'qty' => $line->qty,
                'unit_price' => self::itemUnitPrice($line->item, $line->unit_id),
                'sort' => ++$sort,
            ]);
        }
    }

    /**
     * Sisa kuota paket aktif pasien, paket tertua dipakai dulu.
     *
     * @return list<array{package: PatientPackage, left: array<int, int>}>
     */
    private static function availableQuota(int $patientId): array
    {
        return PatientPackage::query()
            ->where('patient_id', $patientId)
            ->active()
            ->orderBy('start_date')
            ->get()
            ->map(fn (PatientPackage $package): array => ['package' => $package, 'left' => $package->remainingQuota()])
            ->all();
    }

    /** @param list<array{package: PatientPackage, left: array<int, int>}> $quota */
    private static function takeQuota(array &$quota, int $treatmentId, int $qty): ?PatientPackage
    {
        foreach ($quota as &$entry) {
            if (($entry['left'][$treatmentId] ?? 0) >= $qty) {
                $entry['left'][$treatmentId] -= $qty;

                return $entry['package'];
            }
        }

        return null;
    }

    /** @param array<int, InvoiceItem> $items */
    private static function recordPackageUsages(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            /** @var PatientPackage $package */
            $package = PatientPackage::query()->lockForUpdate()->findOrFail($item->patient_package_id);
            $qty = (int) $item->qty;

            if (($package->remainingQuota()[$item->treatment_id] ?? 0) < $qty) {
                throw new InvalidArgumentException("Kuota paket {$package->package_number} untuk \"{$item->description}\" sudah habis. Muat ulang tagihan dari kunjungan.");
            }

            for ($i = 0; $i < $qty; $i++) {
                PackageUsage::create([
                    'patient_package_id' => $package->id,
                    'treatment_id' => $item->treatment_id,
                    'visit_id' => $invoice->visit_id,
                    'invoice_item_id' => $item->id,
                    'used_at' => now(),
                ]);
            }

            PackageSales::completeIfDone($package);
        }
    }

    private static function ensureDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new InvalidArgumentException('Tagihan '.$invoice->label().' sudah diterbitkan dan tidak bisa diubah.');
        }
    }
}
