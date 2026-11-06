<?php

namespace App\Reports;

use App\Models\ItemBatch;
use App\Models\User;

/** Batch berstok yang sudah / akan kedaluwarsa (≤ ambang hari peringatan), dengan nilai. */
class ExpiryReport extends Report
{
    public static function key(): string
    {
        return 'kedaluwarsa';
    }

    public static function label(): string
    {
        return 'Kedaluwarsa';
    }

    public static function description(): string
    {
        return 'Batch berstok yang ED-nya sudah lewat atau jatuh dalam '.(int) config('clinic.stock.expiry_warning_days', 60).' hari ke depan, per hari ini.';
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
            ['label' => 'Barang', 'type' => 'text'], ['label' => 'Batch', 'type' => 'text'], ['label' => 'ED', 'type' => 'text'],
            ['label' => 'Cabang', 'type' => 'text'], ['label' => 'Stok', 'type' => 'number'], ['label' => 'Satuan', 'type' => 'text'],
            ['label' => 'Nilai', 'type' => 'money'], ['label' => 'Status', 'type' => 'text'],
        ];
    }

    public function rows(): array
    {
        $days = (int) config('clinic.stock.expiry_warning_days', 60);

        return ItemBatch::query()->with(['item.baseUnit', 'branch'])->whereIn('branch_id', $this->filters->branchIds)
            ->where('is_unbatched', false)->where('qty_on_hand', '>', 0)->whereNotNull('expired_at')
            ->whereDate('expired_at', '<=', today()->addDays($days))->orderBy('expired_at')->get()
            ->map(fn (ItemBatch $b): array => [
                $b->item->name, (string) $b->batch_number, $b->expired_at?->format('d/m/Y'), $b->branch->name,
                round((float) $b->qty_on_hand, 3), $b->item->baseUnit->name, (int) round((float) $b->qty_on_hand * $b->unit_cost),
                $b->isExpired() ? 'Sudah lewat' : $b->daysToExpiry().' hari lagi',
            ])->all();
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), [6]);
    }
}
