<?php

namespace App\Reports;

use App\Enums\StockMovementStatus;
use App\Models\Item;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

/** Stok & mutasi per barang: saldo awal, masuk, keluar, saldo akhir, nilai akhir (harga pokok batch). */
class StockReport extends Report
{
    public static function key(): string
    {
        return 'stok';
    }

    public static function label(): string
    {
        return 'Stok & mutasi';
    }

    public static function description(): string
    {
        return 'Dari mutasi stok yang sudah tercatat, menurut tanggal mutasi, dalam satuan dasar. Nilai akhir = saldo akhir × harga pokok rata-rata batch saat ini.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports');
    }

    public function columns(): array
    {
        return [
            ['label' => 'Barang', 'type' => 'text'], ['label' => 'Satuan', 'type' => 'text'], ['label' => 'Saldo awal', 'type' => 'number'],
            ['label' => 'Masuk', 'type' => 'number'], ['label' => 'Keluar', 'type' => 'number'], ['label' => 'Saldo akhir', 'type' => 'number'],
            ['label' => 'Nilai akhir', 'type' => 'money'],
        ];
    }

    public function rows(): array
    {
        $base = fn () => DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('stock_movements.status', StockMovementStatus::Posted->value)
            ->whereIn('stock_movements.branch_id', $this->filters->branchIds);

        $opening = $base()->whereDate('stock_movements.movement_date', '<', $this->filters->from->toDateString())
            ->groupBy('stock_movement_lines.item_id')->selectRaw('item_id, sum(qty) as qty')->pluck('qty', 'item_id');
        $flows = $base()->whereDate('stock_movements.movement_date', '>=', $this->filters->from->toDateString())
            ->whereDate('stock_movements.movement_date', '<=', $this->filters->to->toDateString())
            ->groupBy('stock_movement_lines.item_id')
            ->selectRaw('item_id, sum(case when qty > 0 then qty else 0 end) as qty_in, sum(case when qty < 0 then -qty else 0 end) as qty_out')
            ->get()->keyBy('item_id');

        $itemIds = $opening->keys()->merge($flows->keys())->unique();

        return Item::withTrashed()->with('baseUnit')->whereIn('id', $itemIds)->orderBy('name')->get()
            ->map(function (Item $item) use ($opening, $flows): array {
                $start = (float) ($opening[$item->id] ?? 0);
                $in = (float) ($flows[$item->id]->qty_in ?? 0);
                $out = (float) ($flows[$item->id]->qty_out ?? 0);
                $end = $start + $in - $out;
                $cost = count($this->filters->branchIds) === 1 ? StockLedger::averageCost($item->id, $this->filters->branchIds[0]) : StockLedger::averageCost($item->id);

                return [$item->name, $item->baseUnit->name, round($start, 3), round($in, 3), round($out, 3), round($end, 3), (int) round(max(0, $end) * $cost)];
            })->all();
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), [6]);
    }
}
