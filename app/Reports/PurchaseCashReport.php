<?php

namespace App\Reports;

use App\Models\PurchaseCashEntry;
use App\Models\User;

/** Kas pembelian per cabang dalam periode: setoran, pembayaran pesanan, pengeluaran lain, koreksi (edisi inventori). */
class PurchaseCashReport extends Report
{
    public static function key(): string
    {
        return 'kas-pembelian';
    }

    public static function label(): string
    {
        return 'Kas pembelian';
    }

    public static function description(): string
    {
        return 'Setiap uang masuk/keluar kas pembelian dalam periode: setoran, pembayaran pesanan, pengeluaran lain, pengembalian, dan koreksi.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:PurchaseCash');
    }

    public function columns(): array
    {
        return [
            ['label' => 'Tanggal', 'type' => 'text'], ['label' => 'Cabang', 'type' => 'text'], ['label' => 'Jenis', 'type' => 'text'],
            ['label' => 'Keterangan', 'type' => 'text'], ['label' => 'Masuk', 'type' => 'money'], ['label' => 'Keluar', 'type' => 'money'],
            ['label' => 'Dicatat oleh', 'type' => 'text'],
        ];
    }

    public function rows(): array
    {
        return PurchaseCashEntry::query()->with(['branch', 'creator'])
            ->whereIn('branch_id', $this->filters->branchIds)
            ->whereDate('entry_date', '>=', $this->filters->from->toDateString())
            ->whereDate('entry_date', '<=', $this->filters->to->toDateString())
            ->orderBy('entry_date')->orderBy('id')->get()
            ->map(fn (PurchaseCashEntry $e): array => [
                $e->entry_date->format('d/m/Y'), $e->branch->name, $e->type->getLabel(), $e->description,
                $e->amount > 0 ? $e->amount : null, $e->amount < 0 ? -$e->amount : null, $e->creator?->name,
            ])->all();
    }

    public function totals(): ?array
    {
        $rows = $this->rows();

        return ['Total', null, null, null, array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), null];
    }
}
