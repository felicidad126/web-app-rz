<?php

namespace App\Services\Stock;

use App\Enums\InvoiceItemType;
use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReason;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Prescription;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\User;
use App\Models\Visit;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buku besar stok. Semua perubahan stok lewat kelas ini, sebagai dokumen mutasi bernomor.
 *
 * - Jumlah dalam satuan dasar barang; baris bertanda (+ masuk, − keluar).
 * - Keluar memakai FEFO: batch dengan ED terdekat (yang belum kedaluwarsa) diambil dulu.
 * - Bila stok tercatat tidak cukup, sisanya dicatat di batch "tanpa batch" (boleh minus) agar pelayanan tidak
 *   tertahan; angka minus itu muncul di peringatan dan diselesaikan lewat stok opname.
 * - Pemusnahan & koreksi butuh persetujuan (izin Approve:Stock, penyetuju ≠ pembuat).
 */
final class StockLedger
{
    private const EPSILON = 0.0005;

    /**
     * Penerimaan barang dari supplier (langsung tercatat).
     *
     * @param  list<array{item_id: int, unit_id: int, qty: float|int|string, unit_price: int|string, batch_number?: string|null, expired_at?: mixed}>  $lines
     */
    public static function receive(int $branchId, array $lines, ?int $supplierId = null, ?string $invoiceNo = null, mixed $date = null, ?string $notes = null, ?User $user = null): StockMovement
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Isi minimal satu barang yang diterima.');
        }

        return DB::transaction(function () use ($branchId, $lines, $supplierId, $invoiceNo, $date, $notes, $user): StockMovement {
            $movement = self::newMovement(StockMovementType::Receipt, $branchId, $date, $notes, $user, ['supplier_id' => $supplierId, 'supplier_invoice_no' => $invoiceNo]);

            foreach ($lines as $line) {
                $item = Item::with('units')->findOrFail($line['item_id']);
                $entered = (float) $line['qty'];

                if ($entered <= 0) {
                    throw new InvalidArgumentException("Jumlah {$item->name} harus lebih dari 0.");
                }

                $base = $item->toBaseQuantity($entered, (int) $line['unit_id']);
                $factor = $base / $entered;
                $total = (int) round($entered * (int) $line['unit_price']);

                $movement->lines()->create([
                    'item_id' => $item->id,
                    'entered_qty' => $entered,
                    'entered_unit_id' => (int) $line['unit_id'],
                    'qty' => $base,
                    'unit_cost' => (int) round((int) $line['unit_price'] / $factor),
                    'line_cost' => $total,
                    'batch_number' => self::clean($line['batch_number'] ?? null),
                    'expired_at' => ExpiryDate::parse($line['expired_at'] ?? null),
                ]);
            }

            self::apply($movement, $user);

            return $movement->refresh();
        });
    }

    /**
     * Pengeluaran barang (pemakaian manual, resep diserahkan, penjualan) — langsung tercatat, FEFO.
     *
     * @param  list<array{item_id: int, unit_id?: int|null, qty: float|int|string, visit_procedure_id?: int|null, notes?: string|null}>  $lines
     */
    public static function issue(int $branchId, StockMovementType $type, array $lines, ?Model $source = null, ?string $notes = null, ?User $user = null, mixed $date = null): StockMovement
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Isi minimal satu barang.');
        }

        return DB::transaction(function () use ($branchId, $type, $lines, $source, $notes, $user, $date): StockMovement {
            $movement = self::newMovement($type, $branchId, $date, $notes, $user, $source ? [
                'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(),
            ] : []);

            foreach ($lines as $line) {
                $item = Item::with('units')->findOrFail($line['item_id']);
                $base = $item->toBaseQuantity((float) $line['qty'], (int) ($line['unit_id'] ?? $item->base_unit_id));

                if ($base <= 0) {
                    throw new InvalidArgumentException("Jumlah {$item->name} harus lebih dari 0.");
                }

                self::issueFefo($movement, $item, $base, $line['visit_procedure_id'] ?? null, $line['notes'] ?? null);
            }

            self::finalize($movement, $user);

            return $movement->refresh();
        });
    }

    /** Pemakaian bahan sesuai BOM tindakan, dicatat saat rekam medis ditandatangani (sekali per kunjungan). */
    public static function usageForVisit(Visit $visit, ?User $user = null): ?StockMovement
    {
        if (self::existing(StockMovementType::Usage, $visit) !== null) {
            return null;
        }

        $visit->loadMissing('procedures.treatment.materials.item.units');
        $lines = [];

        foreach ($visit->procedures as $procedure) {
            foreach ($procedure->treatment->materials as $material) {
                $lines[] = [
                    'item_id' => $material->item_id,
                    'unit_id' => $material->unit_id,
                    'qty' => (float) $material->qty * max(1, $procedure->qty),
                    'visit_procedure_id' => $procedure->id,
                    'notes' => $procedure->treatment->name.($procedure->tooth_number ? ' · gigi '.$procedure->tooth_number : ''),
                ];
            }
        }

        return $lines === [] ? null : self::issue($visit->branch_id, StockMovementType::Usage, $lines, $visit, 'Pemakaian bahan kunjungan '.$visit->visit_number, $user);
    }

    /** Obat keluar saat resep diserahkan (sekali per resep). */
    public static function dispensePrescription(Prescription $prescription, ?User $user = null): ?StockMovement
    {
        if (self::existing(StockMovementType::Dispensing, $prescription) !== null) {
            return null;
        }

        $prescription->loadMissing('items', 'visit');
        $lines = $prescription->items->map(fn ($line): array => [
            'item_id' => $line->item_id, 'unit_id' => $line->unit_id, 'qty' => (float) $line->qty,
        ])->all();

        return $lines === [] ? null : self::issue($prescription->visit->branch_id, StockMovementType::Dispensing, $lines, $prescription, 'Resep kunjungan '.$prescription->visit->visit_number, $user);
    }

    /** Barang retail yang dijual lewat kasir (bukan obat resep) keluar saat tagihan terbit. */
    public static function saleForInvoice(Invoice $invoice, ?User $user = null): ?StockMovement
    {
        if (self::existing(StockMovementType::Sale, $invoice) !== null) {
            return null;
        }

        $lines = $invoice->items()->get()
            ->filter(fn ($item): bool => $item->item_type === InvoiceItemType::Item && $item->item_id !== null && $item->prescription_item_id === null)
            ->map(fn ($item): array => ['item_id' => $item->item_id, 'unit_id' => $item->unit_id, 'qty' => (float) $item->qty])
            ->values()->all();

        return $lines === [] ? null : self::issue($invoice->branch_id, StockMovementType::Sale, $lines, $invoice, 'Penjualan '.$invoice->label(), $user);
    }

    /** Mutasi balik (mis. tagihan penjualan di-void): setiap baris dikembalikan ke batch asalnya. */
    public static function reverse(StockMovement $movement, string $reason, ?User $user = null): StockMovement
    {
        if (! $movement->isPosted()) {
            throw new InvalidArgumentException('Hanya mutasi yang sudah tercatat yang bisa dibatalkan.');
        }

        $existing = StockMovement::query()->where('reverses_movement_id', $movement->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($movement, $reason, $user): StockMovement {
            $reversal = self::newMovement(StockMovementType::Reversal, $movement->branch_id, null, $reason, $user, [
                'reverses_movement_id' => $movement->id,
                'source_type' => $movement->source_type,
                'source_id' => $movement->source_id,
            ]);

            foreach ($movement->lines()->get() as $line) {
                $reversal->lines()->create([
                    'item_id' => $line->item_id,
                    'item_batch_id' => $line->item_batch_id,
                    'visit_procedure_id' => $line->visit_procedure_id,
                    'entered_qty' => -(float) $line->entered_qty,
                    'entered_unit_id' => $line->entered_unit_id,
                    'qty' => -(float) $line->qty,
                    'unit_cost' => $line->unit_cost,
                    'line_cost' => -$line->line_cost,
                    'batch_number' => $line->batch_number,
                    'expired_at' => $line->expired_at,
                ]);
            }

            self::apply($reversal, $user);

            return $reversal->refresh();
        });
    }

    /**
     * Pembatalan manual oleh admin (izin Approve:Stock): salah input penerimaan, atau bahan/obat yang dikembalikan.
     * Penerimaan yang barangnya sudah terpakai tidak bisa dibatalkan (stok batch akan minus); koreksi lewat opname.
     */
    public static function cancel(StockMovement $movement, string $reason, User $user): StockMovement
    {
        if (! $user->can('Approve:Stock')) {
            throw new InvalidArgumentException('Hanya admin klinik yang bisa membatalkan mutasi.');
        }

        if (! in_array($movement->type, [StockMovementType::Receipt, StockMovementType::Usage, StockMovementType::Dispensing], true)) {
            throw new InvalidArgumentException('Mutasi '.$movement->type->getLabel().' tidak dibatalkan dari sini.');
        }

        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException('Tulis alasan pembatalan (minimal 5 karakter).');
        }

        if (StockMovement::query()->where('reverses_movement_id', $movement->id)->exists()) {
            throw new InvalidArgumentException('Mutasi ini sudah dibatalkan.');
        }

        if ($movement->type === StockMovementType::Receipt) {
            foreach ($movement->lines()->with('batch', 'item')->get() as $line) {
                if ($line->batch !== null && (float) $line->batch->qty_on_hand < (float) $line->qty) {
                    throw new InvalidArgumentException($line->item->name.' dari penerimaan ini sudah terpakai sebagian. Koreksi lewat stok opname.');
                }
            }
        }

        return self::reverse($movement, trim($reason), $user);
    }

    /**
     * Transfer antar cabang: keluar FEFO dari cabang asal, masuk ke cabang tujuan dengan batch, ED, dan harga pokok yang sama.
     *
     * @param  list<array{item_id: int, unit_id?: int|null, qty: float|int|string}>  $lines
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public static function transfer(int $fromBranchId, int $toBranchId, array $lines, ?string $notes = null, ?User $user = null): array
    {
        if ($fromBranchId === $toBranchId) {
            throw new InvalidArgumentException('Cabang asal dan tujuan harus berbeda.');
        }

        return DB::transaction(function () use ($fromBranchId, $toBranchId, $lines, $notes, $user): array {
            $out = self::newMovement(StockMovementType::TransferOut, $fromBranchId, null, $notes, $user, ['counterpart_branch_id' => $toBranchId]);

            foreach ($lines as $line) {
                $item = Item::with('units')->findOrFail($line['item_id']);
                $base = $item->toBaseQuantity((float) $line['qty'], (int) ($line['unit_id'] ?? $item->base_unit_id));
                $available = self::available($item->id, $fromBranchId);

                if ($base <= 0 || $base > $available + self::EPSILON) {
                    throw new InvalidArgumentException("Stok {$item->name} di cabang asal tidak cukup (tersedia ".self::format($available).').');
                }

                self::issueFefo($out, $item, $base, null, null);
            }

            self::finalize($out, $user);

            $in = self::newMovement(StockMovementType::TransferIn, $toBranchId, null, $notes, $user, [
                'counterpart_branch_id' => $fromBranchId, 'source_type' => $out->getMorphClass(), 'source_id' => $out->id,
            ]);

            foreach ($out->lines()->with('batch')->get() as $line) {
                $in->lines()->create([
                    'item_id' => $line->item_id,
                    'entered_qty' => -(float) $line->qty,
                    'entered_unit_id' => $line->entered_unit_id,
                    'qty' => -(float) $line->qty,
                    'unit_cost' => $line->unit_cost,
                    'line_cost' => -$line->line_cost,
                    'batch_number' => $line->batch?->batch_number,
                    'expired_at' => $line->batch?->expired_at,
                ]);
            }

            self::apply($in, $user);

            return [$out->refresh(), $in->refresh()];
        });
    }

    /**
     * Ajukan pemusnahan (kedaluwarsa/rusak/hilang) dari batch tertentu. Tercatat setelah disetujui.
     *
     * @param  list<array{item_batch_id: int, qty: float|int|string}>  $lines  qty dalam satuan dasar
     */
    public static function requestDisposal(int $branchId, array $lines, StockReason $reason, ?string $notes, User $user): StockMovement
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Pilih batch yang dimusnahkan.');
        }

        return DB::transaction(function () use ($branchId, $lines, $reason, $notes, $user): StockMovement {
            $movement = self::newMovement(StockMovementType::Disposal, $branchId, null, $notes, $user, ['reason' => $reason]);

            foreach ($lines as $line) {
                /** @var ItemBatch $batch */
                $batch = ItemBatch::with('item')->findOrFail($line['item_batch_id']);
                $qty = (float) $line['qty'];

                if ($batch->branch_id !== $branchId || $qty <= 0 || $qty > (float) $batch->qty_on_hand + self::EPSILON) {
                    throw new InvalidArgumentException("Jumlah pemusnahan {$batch->item->name} ({$batch->label()}) tidak sesuai stok batch.");
                }

                $movement->lines()->create([
                    'item_id' => $batch->item_id,
                    'item_batch_id' => $batch->id,
                    'entered_qty' => -$qty,
                    'entered_unit_id' => $batch->item->base_unit_id,
                    'qty' => -$qty,
                    'unit_cost' => $batch->unit_cost,
                    'line_cost' => -(int) round($qty * $batch->unit_cost),
                ]);
            }

            $movement->forceFill([
                'status' => StockMovementStatus::Pending,
                'submitted_at' => now(),
                'total_cost' => (int) $movement->lines()->sum('line_cost'),
            ])->save();

            return $movement->refresh();
        });
    }

    /** Setujui mutasi yang menunggu persetujuan (pemusnahan). */
    public static function approve(StockMovement $movement, User $approver): void
    {
        if ($movement->status !== StockMovementStatus::Pending) {
            throw new InvalidArgumentException('Mutasi ini tidak sedang menunggu persetujuan.');
        }

        self::ensureFourEyes($movement->created_by, $approver);

        DB::transaction(function () use ($movement, $approver): void {
            foreach ($movement->lines()->get() as $line) {
                $batch = ItemBatch::query()->lockForUpdate()->find($line->item_batch_id);

                if ($batch !== null && (float) $line->qty < 0 && -(float) $line->qty > (float) $batch->qty_on_hand + self::EPSILON) {
                    throw new InvalidArgumentException('Stok batch sudah berubah sejak pengajuan; ajukan ulang dengan jumlah terbaru.');
                }
            }

            $movement->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();
            self::apply($movement, $approver);
        });
    }

    public static function reject(StockMovement $movement, User $approver, string $reason): void
    {
        if ($movement->status !== StockMovementStatus::Pending) {
            throw new InvalidArgumentException('Mutasi ini tidak sedang menunggu persetujuan.');
        }

        self::ensureFourEyes($movement->created_by, $approver);

        $movement->forceFill([
            'status' => StockMovementStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejected_reason' => $reason,
        ])->save();
    }

    /** Stok tersedia (satuan dasar) untuk dikeluarkan: batch belum kedaluwarsa di cabang. */
    public static function available(int $itemId, int $branchId): float
    {
        return (float) ItemBatch::query()
            ->where('item_id', $itemId)->where('branch_id', $branchId)->where('is_unbatched', false)
            ->where('qty_on_hand', '>', 0)
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhereDate('expired_at', '>=', today()))
            ->sum('qty_on_hand');
    }

    /** Harga pokok rata-rata per satuan dasar (stok yang ada di cabang; bila kosong, batch terakhir di cabang mana pun). */
    public static function averageCost(int $itemId, ?int $branchId = null): int
    {
        $query = ItemBatch::query()->where('item_id', $itemId)->where('is_unbatched', false)->where('qty_on_hand', '>', 0);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $row = $query->selectRaw('sum(qty_on_hand * unit_cost) as value, sum(qty_on_hand) as qty')->toBase()->first();

        if ($row !== null && (float) $row->qty > 0) {
            return (int) round((float) $row->value / (float) $row->qty);
        }

        return (int) ItemBatch::query()->where('item_id', $itemId)->where('is_unbatched', false)->where('unit_cost', '>', 0)->latest('id')->value('unit_cost');
    }

    public static function format(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }

    // ── internal ─────────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public static function newMovement(StockMovementType $type, int $branchId, mixed $date, ?string $notes, ?User $user, array $attributes = []): StockMovement
    {
        $branch = Branch::findOrFail($branchId);
        $date = $date ? Carbon::parse($date) : today();

        $movement = new StockMovement([
            ...$attributes,
            'number' => NumberSequence::nextStockNumber($type->prefix(), $branch->code, $date),
            'branch_id' => $branchId,
            'type' => $type,
            'status' => StockMovementStatus::Draft,
            'movement_date' => $date,
            'notes' => $notes,
        ]);

        if ($user !== null) {
            $movement->created_by = $user->id;
        }

        $movement->save();

        return $movement;
    }

    /** Ambil dari batch FEFO (mengurangi cache saat itu juga) dan tulis barisnya. */
    private static function issueFefo(StockMovement $movement, Item $item, float $baseQty, ?int $visitProcedureId, ?string $notes): void
    {
        $remaining = $baseQty;
        $batches = ItemBatch::query()
            ->where('item_id', $item->id)->where('branch_id', $movement->branch_id)->where('is_unbatched', false)
            ->where('qty_on_hand', '>', 0)
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhereDate('expired_at', '>=', today()))
            ->orderByRaw('case when expired_at is null then 1 else 0 end')
            ->orderBy('expired_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= self::EPSILON) {
                break;
            }

            $take = min($remaining, (float) $batch->qty_on_hand);
            self::writeIssueLine($movement, $item, $batch, $take, $visitProcedureId, $notes);
            $remaining -= $take;
        }

        if ($remaining > self::EPSILON) {
            // Stok tercatat tidak cukup: catat sebagai "tanpa batch" (minus) agar pelayanan jalan; diselesaikan lewat opname.
            $unbatched = ItemBatch::query()->lockForUpdate()->firstOrCreate(
                ['item_id' => $item->id, 'branch_id' => $movement->branch_id, 'is_unbatched' => true],
                ['unit_cost' => self::averageCost($item->id, $movement->branch_id)],
            );
            self::writeIssueLine($movement, $item, $unbatched, $remaining, $visitProcedureId, trim(($notes ? $notes.' · ' : '').'stok tercatat tidak cukup'));
        }
    }

    private static function writeIssueLine(StockMovement $movement, Item $item, ItemBatch $batch, float $qty, ?int $visitProcedureId, ?string $notes): void
    {
        $movement->lines()->create([
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'visit_procedure_id' => $visitProcedureId,
            'entered_qty' => -$qty,
            'entered_unit_id' => $item->base_unit_id,
            'qty' => -$qty,
            'unit_cost' => $batch->unit_cost,
            'line_cost' => -(int) round($qty * $batch->unit_cost),
            'notes' => $notes ? mb_strimwidth($notes, 0, 250, '…') : null,
        ]);

        $batch->forceFill(['qty_on_hand' => (float) $batch->qty_on_hand - $qty])->save();
    }

    /** Tandai mutasi keluar (yang barisnya sudah diterapkan saat FEFO) sebagai tercatat. */
    private static function finalize(StockMovement $movement, ?User $user): void
    {
        $movement->forceFill([
            'status' => StockMovementStatus::Posted,
            'total_cost' => (int) $movement->lines()->sum('line_cost'),
            'posted_by' => $user->id ?? auth()->id(),
            'posted_at' => now(),
        ])->save();
    }

    /** Terapkan semua baris ke saldo batch (membuat batch baru untuk barang masuk tanpa batch) lalu tandai tercatat. */
    public static function apply(StockMovement $movement, ?User $user): void
    {
        foreach ($movement->lines()->get() as $line) {
            $batch = $line->item_batch_id !== null
                ? ItemBatch::query()->lockForUpdate()->findOrFail($line->item_batch_id)
                : self::batchFor($movement->branch_id, $line);

            $qty = (float) $line->qty;
            $current = (float) $batch->qty_on_hand;

            // Barang masuk ke batch yang sama dengan harga berbeda: harga pokok jadi rata-rata tertimbang.
            if ($qty > 0 && ! $batch->is_unbatched && $current > 0 && $line->unit_cost !== $batch->unit_cost) {
                $batch->unit_cost = (int) round(($current * $batch->unit_cost + $qty * $line->unit_cost) / ($current + $qty));
            } elseif ($qty > 0 && ($current <= 0 || $batch->unit_cost === 0)) {
                $batch->unit_cost = $line->unit_cost;
            }

            $batch->qty_on_hand = (string) ($current + $qty);
            $batch->save();

            if ($line->item_batch_id === null) {
                $line->forceFill(['item_batch_id' => $batch->id])->save();
            }
        }

        $movement->forceFill([
            'status' => StockMovementStatus::Posted,
            'total_cost' => (int) $movement->lines()->sum('line_cost'),
            'posted_by' => $user->id ?? auth()->id(),
            'posted_at' => now(),
        ])->save();
    }

    private static function batchFor(int $branchId, StockMovementLine $line): ItemBatch
    {
        return ItemBatch::query()->lockForUpdate()->firstOrCreate(
            [
                'item_id' => $line->item_id,
                'branch_id' => $branchId,
                'batch_number' => $line->batch_number,
                'expired_at' => $line->expired_at?->toDateString(),
                'is_unbatched' => false,
            ],
            ['unit_cost' => $line->unit_cost, 'received_at' => today()],
        );
    }

    private static function existing(StockMovementType $type, Model $source): ?StockMovement
    {
        return StockMovement::query()
            ->where('type', $type)->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->where('status', StockMovementStatus::Posted)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as r')->whereColumn('r.reverses_movement_id', 'stock_movements.id'))
            ->first();
    }

    public static function ensureFourEyes(?int $createdBy, User $approver): void
    {
        if (! $approver->can('Approve:Stock')) {
            throw new InvalidArgumentException('Anda tidak punya izin menyetujui mutasi stok.');
        }

        if ($createdBy !== null && $createdBy === $approver->id) {
            throw new InvalidArgumentException('Pengajuan tidak bisa disetujui oleh orang yang sama.');
        }
    }

    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
