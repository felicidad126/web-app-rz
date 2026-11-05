<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Visit;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Data panel kanan halaman kasir: daftar "Sebelum pasien pulang" dan saldo terutang lain.
 */
final class CheckoutPanel
{
    /**
     * @return list<array{label: string, done: bool, detail: string|null, action?: string}>
     */
    public static function checklist(Invoice $invoice): array
    {
        $items = [];
        /** @var Visit|null $visit */
        $visit = $invoice->visit_id ? Visit::with(['medicalRecord', 'prescription.items.item'])->find($invoice->visit_id) : null;

        if ($visit !== null) {
            $signedAt = $visit->medicalRecord?->signed_at;
            $items[] = [
                'label' => 'Rekam medis ditandatangani dokter',
                'done' => $signedAt !== null,
                'detail' => $signedAt?->format('H:i') ?? 'belum, tagihan tetap bisa dibuat',
            ];

            $prescription = $visit->prescription;

            if ($prescription !== null && $prescription->items->isNotEmpty()) {
                $items[] = [
                    'label' => 'Resep diserahkan',
                    'done' => $prescription->dispensed_at !== null,
                    'detail' => $prescription->items->map(fn ($line): string => $line->item->name.' × '.rtrim(rtrim((string) $line->qty, '0'), '.'))->implode(', '),
                    'action' => $prescription->dispensed_at === null ? 'markDispensed' : null,
                ];
            }
        }

        $next = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::Confirmed])
            ->where('patient_id', $invoice->patient_id)->where('start_at', '>', now())
            ->with(['practitioner.user', 'chair'])->orderBy('start_at')->first();

        $items[] = [
            'label' => 'Janji berikutnya',
            'done' => $next !== null,
            'detail' => $next
                ? $next->start_at->translatedFormat('D, d M Y H:i').' · '.$next->practitioner->user->name.($next->reason ? ' · '.$next->reason : '')
                : 'belum ada (buat lewat Jadwal & antrean bila perlu kontrol)',
        ];

        $items[] = [
            'label' => 'Pembayaran',
            'done' => $invoice->status === InvoiceStatus::Paid,
            'detail' => match ($invoice->status) {
                InvoiceStatus::Paid => 'lunas',
                InvoiceStatus::Partial => 'sisa '.Money::format($invoice->balance()),
                default => null,
            },
        ];

        return $items;
    }

    /** @return Collection<int, Invoice> tagihan lain pasien yang belum lunas (cabang yang boleh diakses) */
    public static function otherOutstanding(Invoice $invoice): Collection
    {
        return Invoice::query()
            ->outstanding()
            ->where('patient_id', $invoice->patient_id)
            ->whereKeyNot($invoice->id)
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->orderBy('issued_at')
            ->get();
    }
}
