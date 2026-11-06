<?php

namespace App\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\Kpis;

/** Piutang pasien saat ini per tagihan, dengan kelompok umur (Kpi::Receivables). */
class ReceivablesReport extends Report
{
    public static function key(): string
    {
        return 'piutang';
    }

    public static function label(): string
    {
        return 'Piutang';
    }

    public static function description(): string
    {
        return 'Tagihan terbit yang belum lunas per hari ini. Umur dihitung dari tanggal terbit; sisa = porsi pasien − dibayar.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports');
    }

    public static function usesPeriod(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            ['label' => 'No. tagihan', 'type' => 'text'], ['label' => 'Terbit', 'type' => 'text'], ['label' => 'Pasien', 'type' => 'text'],
            ['label' => 'No. RM', 'type' => 'text'], ['label' => 'Cabang', 'type' => 'text'], ['label' => 'Porsi pasien', 'type' => 'money'],
            ['label' => 'Dibayar', 'type' => 'money'], ['label' => 'Sisa', 'type' => 'money'], ['label' => 'Umur (hari)', 'type' => 'number'],
            ['label' => 'Kelompok', 'type' => 'text'],
        ];
    }

    public function rows(): array
    {
        return Invoice::query()->with(['patient', 'branch'])->whereIn('branch_id', $this->filters->branchIds)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])->orderBy('issued_at')->get()
            ->map(function (Invoice $i): array {
                $age = max(0, (int) ($i->issued_at ?? now())->copy()->startOfDay()->diffInDays(today()));

                return [$i->label(), $i->issued_at?->format('d/m/Y'), $i->patient->name, $i->patient->rm_number, $i->branch->name,
                    $i->patient_portion, $i->paid_total, max(0, $i->patient_portion - $i->paid_total), $age, Kpis::bucket($age).' hari'];
            })->all();
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), [5, 6, 7]);
    }
}
