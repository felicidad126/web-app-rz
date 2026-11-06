<?php

namespace App\Services\Commission;

use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\PayoutStatus;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PatientPackage;
use App\Models\Refund;
use App\Models\Treatment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jasa dokter/asisten (Planning §5.7, keputusan owner 6 Nov 2026).
 *
 * Alur:
 * 1. Saat tagihan diterbitkan (sebelum baris dikunci): aturan yang berlaku di-snapshot ke baris tindakan, dan
 *    baris tindakan yang tercakup paket diberi nilai paket per pemakaian (`package_value`).
 * 2. Setelah terbit: satu entri jasa per baris tindakan per peran, dari snapshot itu.
 * 3. Pengakuan: "saat tindakan dikerjakan" → langsung diakui; "saat lunas" → menunggu sampai tagihan lunas.
 * 4. Void tagihan / refund → entri dibatalkan (bila belum masuk rekap final) atau dibuat entri pembalik.
 *
 * Basis "setelah diskon" memakai neto baris (diskon baris + alokasi diskon tagihan, sebelum pajak).
 */
final class CommissionEngine
{
    /** Urutan prioritas aturan: semakin kecil semakin spesifik. */
    public const PRIORITY = [
        'practitioner+treatment' => 1,
        'practitioner+fee_group' => 2,
        'practitioner' => 3,
        'treatment' => 4,
        'fee_group' => 5,
        'default' => 6,
    ];

    /**
     * Snapshot aturan & nilai paket pada baris tindakan. Dipanggil di dalam transaksi penerbitan, saat tagihan masih draf.
     *
     * @param  iterable<InvoiceItem>  $items
     */
    public static function snapshotItems(Invoice $invoice, iterable $items): void
    {
        $rules = self::activeRules();

        foreach ($items as $item) {
            if ($item->item_type !== InvoiceItemType::Procedure) {
                continue;
            }

            $treatment = $item->treatment_id ? Treatment::withTrashed()->find($item->treatment_id) : null;
            $packageValue = 0;

            if ($item->patient_package_id !== null && $item->unit_price === 0 && $item->treatment_id !== null) {
                $package = PatientPackage::with('items')->find($item->patient_package_id);
                $packageValue = $package ? self::packageUnitValue($package, $item->treatment_id) * (int) round((float) $item->qty) : 0;
            }

            $snapshot = [];

            foreach ([CommissionRole::Operator->value => $item->practitioner_id, CommissionRole::Assistant->value => $item->assistant_id] as $role => $practitionerId) {
                if ($practitionerId === null) {
                    continue;
                }

                $rule = self::resolve($rules, $treatment, $practitionerId, CommissionRole::from($role));

                if ($rule !== null) {
                    $snapshot[$role] = [...$rule->snapshot(), 'practitioner_id' => $practitionerId];
                }
            }

            $item->forceFill([
                'package_value' => $packageValue,
                'commission_rule_snapshot' => $snapshot === [] ? null : $snapshot,
            ])->save();
        }
    }

    /**
     * Buat entri jasa untuk tagihan yang sudah terbit (idempoten).
     *
     * @return Collection<int, Commission>
     */
    public static function recordForInvoice(Invoice $invoice): Collection
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            return collect();
        }

        $invoice->loadMissing('visit');
        $created = collect();

        DB::transaction(function () use ($invoice, $created): void {
            $existing = Commission::query()->where('invoice_id', $invoice->id)->whereNull('reverses_commission_id')
                ->get(['invoice_item_id', 'role'])->map(fn (Commission $c): string => $c->invoice_item_id.':'.$c->role->value)->all();

            $items = $invoice->items()->where('item_type', InvoiceItemType::Procedure)->whereNotNull('practitioner_id')->get();
            $rules = null;

            foreach ($items as $item) {
                // Baris yang terbit saat belum ada aturan sama sekali: disusulkan dengan aturan yang berlaku sekarang.
                $snapshots = $item->commission_rule_snapshot ?? self::catchUpSnapshot($item, $rules ??= self::activeRules());

                foreach ($snapshots as $role => $snapshot) {
                    if (in_array($item->id.':'.$role, $existing, true)) {
                        continue;
                    }

                    $created->push(self::createEntry($invoice, $item, CommissionRole::from($role), $snapshot));
                }
            }
        });

        return $created;
    }

    /** Tagihan berubah status bayar: jasa "saat lunas" diakui atau kembali menunggu. */
    public static function syncRecognition(Invoice $invoice): void
    {
        $commissions = Commission::query()->where('invoice_id', $invoice->id)->whereIn('status', [CommissionStatus::Pending, CommissionStatus::Earned])->get();

        foreach ($commissions as $commission) {
            if (($commission->rule_snapshot['recognition'] ?? 'performed') !== CommissionRecognition::Paid->value) {
                continue;
            }

            if ($invoice->status === InvoiceStatus::Paid && $commission->status === CommissionStatus::Pending) {
                $commission->forceFill(['status' => CommissionStatus::Earned, 'earned_at' => $invoice->paid_at ?? now()])->save();
            } elseif ($invoice->status !== InvoiceStatus::Paid && $commission->status === CommissionStatus::Earned && $commission->commission_payout_id === null) {
                $commission->forceFill(['status' => CommissionStatus::Pending, 'earned_at' => null])->save();
            }
        }
    }

    /** Tagihan di-void: jasa yang belum masuk rekap final dibatalkan; yang sudah, dibalik di rekap berikutnya. */
    public static function reverseInvoice(Invoice $invoice, string $reason): void
    {
        $rows = Commission::query()->where('invoice_id', $invoice->id)->where('status', '!=', CommissionStatus::Cancelled)
            ->with('payout')->get()
            ->reject(fn (Commission $c): bool => ($c->rule_snapshot['reversal'] ?? null) === 'void');

        $touchedPayouts = [];

        foreach ($rows as $row) {
            if ($row->payout === null || $row->payout->status === PayoutStatus::Draft) {
                if ($row->commission_payout_id !== null) {
                    $touchedPayouts[$row->commission_payout_id] = true;
                }

                $row->forceFill(['status' => CommissionStatus::Cancelled, 'reason' => $reason, 'commission_payout_id' => null])->save();

                continue;
            }

            self::reversal($row, 1.0, 'void', $reason);
        }

        foreach (array_keys($touchedPayouts) as $payoutId) {
            CommissionPayouts::recalculateById($payoutId);
        }
    }

    /** Refund dana tagihan: jasa dibalik sebanding dengan porsi refund terhadap total tagihan. */
    public static function reverseForRefund(Refund $refund): void
    {
        $invoice = $refund->invoice;

        if ($invoice === null || $invoice->total <= 0) {
            return;
        }

        $ratio = min(1.0, $refund->amount / $invoice->total);
        $rows = Commission::query()->where('invoice_id', $invoice->id)->whereNull('reverses_commission_id')
            ->whereIn('status', [CommissionStatus::Pending, CommissionStatus::Earned, CommissionStatus::Paid])->get();

        foreach ($rows as $row) {
            self::reversal($row, $ratio, 'refund', 'Refund '.$invoice->label().': '.$refund->reason);
        }
    }

    /**
     * Pilih aturan paling spesifik.
     *
     * @param  Collection<int, CommissionRule>  $rules
     */
    public static function resolve(Collection $rules, ?Treatment $treatment, int $practitionerId, CommissionRole $role): ?CommissionRule
    {
        return $rules
            ->filter(fn (CommissionRule $rule): bool => $rule->role === $role
                && ($rule->practitioner_id === null || $rule->practitioner_id === $practitionerId)
                && ($rule->treatment_id === null || $rule->treatment_id === $treatment?->id)
                && ($rule->fee_group === null || $rule->fee_group === $treatment?->fee_group))
            ->sortBy(fn (CommissionRule $rule): array => [self::PRIORITY[self::scope($rule)], -$rule->id])
            ->first();
    }

    public static function scope(CommissionRule $rule): string
    {
        return match (true) {
            $rule->practitioner_id !== null && $rule->treatment_id !== null => 'practitioner+treatment',
            $rule->practitioner_id !== null && $rule->fee_group !== null => 'practitioner+fee_group',
            $rule->practitioner_id !== null => 'practitioner',
            $rule->treatment_id !== null => 'treatment',
            $rule->fee_group !== null => 'fee_group',
            default => 'default',
        };
    }

    /**
     * Rumus jasa satu baris.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{base: int, amount: int}
     */
    public static function calculate(array $snapshot, int $gross, int $net, int $material, int $lab, float $qty): array
    {
        // Nominal tetap tidak memakai basis (basis 0 agar "tarif efektif" di rekap tidak menyesatkan).
        if ($snapshot['type'] === CommissionType::Fixed->value) {
            return ['base' => 0, 'amount' => (int) round((int) $snapshot['fixed_amount'] * $qty)];
        }

        $base = CommissionBase::from($snapshot['base'])->amount($gross, $net, $material, $lab);
        $amount = (int) round($base * (float) $snapshot['percent'] / 100);

        return ['base' => $base, 'amount' => $amount];
    }

    /**
     * Nilai paket untuk satu kali pemakaian tindakan: harga paket dibagi ke kuota, ditimbang dengan tarif tindakan.
     * Contoh: paket Rp 8 jt berisi 12× kontrol saja → Rp 666.667 per kontrol.
     */
    public static function packageUnitValue(PatientPackage $package, int $treatmentId): int
    {
        $package->loadMissing('items');
        $treatments = Treatment::withTrashed()->with('branchPrices')->whereIn('id', $package->items->pluck('treatment_id'))->get()->keyBy('id');

        $weights = $package->items->mapWithKeys(fn ($line): array => [
            $line->treatment_id => max(1, $treatments->get($line->treatment_id)?->priceForBranch($package->branch_id) ?? 1),
        ]);
        $total = $package->items->sum(fn ($line): int => $weights[$line->treatment_id] * $line->quota);
        $weight = $weights[$treatmentId] ?? null;

        if ($total <= 0 || $weight === null) {
            return 0;
        }

        return (int) round($package->price * $weight / $total);
    }

    /**
     * Tagihan terbit tanpa entri jasa sama sekali (mis. terbit saat aturan belum ada / pencatatan sempat gagal) di rentang tanggal.
     */
    public static function recordMissing(Carbon $from, Carbon $to): int
    {
        $count = 0;

        Invoice::query()->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereBetween('issued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('items', fn ($q) => $q->where('item_type', InvoiceItemType::Procedure)->whereNotNull('practitioner_id'))
            ->whereDoesntHave('commissions')
            ->each(function (Invoice $invoice) use (&$count): void {
                $count += self::recordForInvoice($invoice)->count();
            });

        return $count;
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @return array<string, array<string, mixed>>
     */
    private static function catchUpSnapshot(InvoiceItem $item, Collection $rules): array
    {
        $treatment = $item->treatment_id ? Treatment::withTrashed()->find($item->treatment_id) : null;
        $snapshot = [];

        foreach ([CommissionRole::Operator->value => $item->practitioner_id, CommissionRole::Assistant->value => $item->assistant_id] as $role => $practitionerId) {
            if ($practitionerId !== null && ($rule = self::resolve($rules, $treatment, $practitionerId, CommissionRole::from($role))) !== null) {
                $snapshot[$role] = [...$rule->snapshot(), 'practitioner_id' => $practitionerId, 'catch_up' => true];
            }
        }

        return $snapshot;
    }

    /** @return Collection<int, CommissionRule> */
    private static function activeRules(): Collection
    {
        return CommissionRule::query()->where('is_active', true)->get();
    }

    /** @param array<string, mixed> $snapshot */
    private static function createEntry(Invoice $invoice, InvoiceItem $item, CommissionRole $role, array $snapshot): Commission
    {
        $isPackage = $item->package_value > 0;
        $qty = (float) $item->qty;
        $gross = $isPackage ? $item->package_value : (int) round($qty * $item->unit_price);
        $net = $isPackage ? $item->package_value : $item->net_amount;
        ['base' => $base, 'amount' => $amount] = self::calculate($snapshot, $gross, $net, $item->material_cost, $item->lab_cost, $qty);

        $recognition = $snapshot['recognition'] ?? CommissionRecognition::Performed->value;
        $earned = $recognition === CommissionRecognition::Performed->value || $invoice->status === InvoiceStatus::Paid;

        return Commission::create([
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $item->id,
            'branch_id' => $invoice->branch_id,
            'practitioner_id' => (int) $snapshot['practitioner_id'],
            'role' => $role,
            'commission_rule_id' => CommissionRule::withTrashed()->whereKey($snapshot['rule_id'] ?? 0)->exists() ? $snapshot['rule_id'] : null,
            'rule_snapshot' => $snapshot,
            'service_date' => $invoice->visit !== null ? $invoice->visit->visit_date : ($invoice->issued_at ?? now()),
            'gross_amount' => $gross,
            'discount_amount' => $gross - $net,
            'net_amount' => $net,
            'material_cost' => $item->material_cost,
            'lab_cost' => $item->lab_cost,
            'base_amount' => $base,
            'amount' => $amount,
            'status' => $earned ? CommissionStatus::Earned : CommissionStatus::Pending,
            'earned_at' => $earned ? ($recognition === CommissionRecognition::Paid->value ? $invoice->paid_at : $invoice->issued_at) ?? now() : null,
        ]);
    }

    /** Entri pembalik (negatif) untuk sebagian atau seluruh entri. */
    private static function reversal(Commission $row, float $ratio, string $kind, string $reason): Commission
    {
        $scale = fn (int $value): int => -(int) round($value * $ratio);
        $earned = $row->status !== CommissionStatus::Pending;

        return Commission::create([
            'invoice_id' => $row->invoice_id,
            'invoice_item_id' => $row->invoice_item_id,
            'branch_id' => $row->branch_id,
            'practitioner_id' => $row->practitioner_id,
            'role' => $row->role,
            'commission_rule_id' => $row->commission_rule_id,
            'rule_snapshot' => [...$row->rule_snapshot, 'reversal' => $kind, 'ratio' => round($ratio, 6)],
            'service_date' => $row->service_date,
            'gross_amount' => $scale($row->gross_amount),
            'discount_amount' => $scale($row->discount_amount),
            'net_amount' => $scale($row->net_amount),
            'material_cost' => $scale($row->material_cost),
            'lab_cost' => $scale($row->lab_cost),
            'base_amount' => $scale($row->base_amount),
            'amount' => $scale($row->amount),
            'status' => $earned ? CommissionStatus::Earned : CommissionStatus::Pending,
            'earned_at' => $earned ? now() : null,
            'reverses_commission_id' => $row->id,
            'reason' => $reason,
        ]);
    }
}
