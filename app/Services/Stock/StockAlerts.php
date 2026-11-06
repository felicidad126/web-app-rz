<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Peringatan stok per cabang dan perkiraan HPP bahan per tindakan.
 */
final class StockAlerts
{
    /** @return array<int, float> item_id → stok (satuan dasar, termasuk minus "tanpa batch") */
    public static function stockByItem(int $branchId): array
    {
        return DB::table('item_batches')->where('branch_id', $branchId)
            ->groupBy('item_id')->selectRaw('item_id, sum(qty_on_hand) as qty')
            ->pluck('qty', 'item_id')->map(fn ($qty): float => (float) $qty)->all();
    }

    /** @return Collection<int, Item> barang aktif dengan stok < minimum */
    public static function belowMinimum(int $branchId): Collection
    {
        $stock = self::stockByItem($branchId);

        return Item::query()->with('baseUnit')->where('is_active', true)->where('min_stock', '>', 0)->get()
            ->filter(fn (Item $item): bool => ($stock[$item->id] ?? 0.0) < (float) $item->min_stock)
            ->values();
    }

    /** @return Collection<int, ItemBatch> batch berstok yang kedaluwarsa dalam $days hari (termasuk yang sudah lewat) */
    public static function expiring(int $branchId, ?int $days = null): Collection
    {
        $days ??= (int) config('clinic.stock.expiry_warning_days', 60);

        return ItemBatch::query()->with('item.baseUnit')
            ->where('branch_id', $branchId)->where('is_unbatched', false)->where('qty_on_hand', '>', 0)
            ->whereNotNull('expired_at')->whereDate('expired_at', '<=', today()->addDays($days))
            ->orderBy('expired_at')->get();
    }

    /** @return Collection<int, ItemBatch> stok minus (dipakai saat stok tercatat habis) */
    public static function negative(int $branchId): Collection
    {
        return ItemBatch::query()->with('item.baseUnit')->where('branch_id', $branchId)->where('qty_on_hand', '<', 0)->get();
    }

    /** Perkiraan HPP bahan untuk 1× tindakan: BOM × harga pokok rata-rata. */
    public static function estimatedMaterialCost(Treatment $treatment, ?int $branchId = null): int
    {
        $treatment->loadMissing('materials.item.units');

        return (int) round($treatment->materials->sum(
            fn ($material): float => $material->baseQty() * StockLedger::averageCost($material->item_id, $branchId),
        ));
    }
}
