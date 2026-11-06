<?php

namespace App\Services\Stock;

use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockOpnameStatus;
use App\Enums\StockReason;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\NumberSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stok opname: snapshot saldo sistem per batch → hitung fisik → diajukan → disetujui (Approve:Stock, penyetuju ≠
 * pembuat) → selisih dicatat sebagai mutasi koreksi. Opname pertama di cabang sekaligus menjadi saldo awal.
 */
final class StockOpnames
{
    public static function start(int $branchId, User $user, ?string $notes = null): StockOpname
    {
        if (StockOpname::query()->where('branch_id', $branchId)->whereIn('status', [StockOpnameStatus::Counting, StockOpnameStatus::Pending])->exists()) {
            throw new InvalidArgumentException('Masih ada stok opname yang belum selesai di cabang ini.');
        }

        return DB::transaction(function () use ($branchId, $user, $notes): StockOpname {
            $branch = Branch::findOrFail($branchId);
            $opname = StockOpname::create([
                'number' => NumberSequence::nextStockNumber('OPN', $branch->code, now()),
                'branch_id' => $branchId,
                'status' => StockOpnameStatus::Counting,
                'started_at' => now(),
                'notes' => $notes,
            ]);
            $opname->forceFill(['created_by' => $user->id])->save();

            $batches = ItemBatch::query()->with('item')
                ->where('branch_id', $branchId)->where('qty_on_hand', '!=', 0)
                ->orderBy('item_id')->orderBy('expired_at')->get();

            foreach ($batches as $batch) {
                $opname->lines()->create([
                    'item_id' => $batch->item_id,
                    'item_batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expired_at' => $batch->expired_at,
                    'unit_cost' => $batch->unit_cost,
                    'system_qty' => $batch->qty_on_hand,
                ]);
            }

            // Barang aktif yang belum punya stok di cabang ini: baris kosong untuk diisi (saldo awal).
            $stocked = $batches->pluck('item_id')->unique()->all();
            Item::query()->where('is_active', true)->whereNotIn('id', $stocked)
                ->whereIn('type', [ItemType::Medicine, ItemType::Material, ItemType::Tool, ItemType::Retail])
                ->orderBy('name')
                ->each(fn (Item $item) => $opname->lines()->create([
                    'item_id' => $item->id,
                    'unit_cost' => StockLedger::averageCost($item->id),
                    'system_qty' => 0,
                ]));

            return $opname->refresh();
        });
    }

    public static function submit(StockOpname $opname, User $user): void
    {
        if (! $opname->isCounting()) {
            throw new InvalidArgumentException('Opname ini sudah diajukan.');
        }

        $missing = $opname->lines()->whereNull('counted_qty')->where('system_qty', '!=', 0)->count();

        if ($missing > 0) {
            throw new InvalidArgumentException("{$missing} batch yang tercatat punya stok belum dihitung. Isi jumlah fisiknya (0 bila tidak ada).");
        }

        $unnamed = $opname->lines()->whereNull('item_batch_id')->where('counted_qty', '>', 0)->where('unit_cost', 0)->with('item')->first();

        if ($unnamed !== null) {
            throw new InvalidArgumentException("Isi harga pokok untuk {$unnamed->item->name} (stok baru ditemukan).");
        }

        $opname->forceFill(['status' => StockOpnameStatus::Pending, 'submitted_at' => now(), 'rejected_reason' => null])->save();
    }

    public static function approve(StockOpname $opname, User $approver): void
    {
        if ($opname->status !== StockOpnameStatus::Pending) {
            throw new InvalidArgumentException('Opname ini tidak sedang menunggu persetujuan.');
        }

        StockLedger::ensureFourEyes($opname->created_by, $approver);

        DB::transaction(function () use ($opname, $approver): void {
            $movement = StockLedger::newMovement(StockMovementType::Adjustment, $opname->branch_id, null, 'Koreksi stok opname '.$opname->number, $approver, [
                'reason' => StockReason::Correction,
                'source_type' => $opname->getMorphClass(),
                'source_id' => $opname->id,
            ]);

            foreach ($opname->lines()->with('item')->get() as $line) {
                $difference = $line->difference();

                if ($difference === null || abs($difference) < 0.0005) {
                    continue;
                }

                $movement->lines()->create([
                    'item_id' => $line->item_id,
                    'item_batch_id' => $line->item_batch_id,
                    'entered_qty' => $difference,
                    'entered_unit_id' => $line->item->base_unit_id,
                    'qty' => $difference,
                    'unit_cost' => $line->unit_cost,
                    'line_cost' => (int) round($difference * $line->unit_cost),
                    'batch_number' => $line->batch_number,
                    'expired_at' => $line->expired_at,
                    'notes' => $line->notes,
                ]);
            }

            $movement->forceFill(['approved_by' => $approver->id, 'approved_at' => now(), 'submitted_at' => $opname->submitted_at])->save();
            StockLedger::apply($movement, $approver);

            $opname->forceFill([
                'status' => StockOpnameStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'adjustment_movement_id' => $movement->id,
            ])->save();
        });
    }

    /** Dikembalikan untuk dihitung ulang (alasan dicatat). */
    public static function reject(StockOpname $opname, User $approver, string $reason): void
    {
        if ($opname->status !== StockOpnameStatus::Pending) {
            throw new InvalidArgumentException('Opname ini tidak sedang menunggu persetujuan.');
        }

        StockLedger::ensureFourEyes($opname->created_by, $approver);

        $opname->forceFill(['status' => StockOpnameStatus::Counting, 'submitted_at' => null, 'rejected_reason' => $reason])->save();
    }
}
