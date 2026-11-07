<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseCashType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseCashEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\NumberSequence;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembelian BHP (alur dari aplikasi inventori KP klinik, disatukan dengan modul stok):
 *
 * 1. Permintaan: perawat/staf meminta barang; disetujui atau ditolak oleh orang lain (izin Approve:Purchase).
 * 2. Pesanan: admin memesan ke supplier (satu pesanan bisa memuat beberapa permintaan). Memesan TIDAK menambah stok.
 * 3. Penerimaan: barang yang benar-benar datang dicatat lewat StockLedger::receive (batch, ED, harga pokok);
 *    bisa sebagian. Jumlah diterima dicek ulang di dalam kunci baris agar tidak melebihi pesanan.
 * 4. Kas pembelian per cabang: setoran, pembayaran pesanan, pengeluaran lain. Saldo tidak boleh minus;
 *    entri tidak bisa diubah, koreksi lewat entri pembalik.
 *
 * Semua kesalahan input dilempar sebagai InvalidArgumentException berpesan Bahasa Indonesia.
 */
final class Purchasing
{
    private const EPSILON = 0.0005;

    // ---------------------------------------------------------------------
    // Permintaan
    // ---------------------------------------------------------------------

    public static function requestItem(int $branchId, int $itemId, float $quantity, int $unitId, ?string $reason, User $user): PurchaseRequest
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah yang diminta harus lebih dari 0.');
        }

        $item = Item::with('units')->findOrFail($itemId);
        $branch = Branch::findOrFail($branchId);

        return PurchaseRequest::create([
            'number' => NumberSequence::nextPurchaseRequestNumber($branch->code, today()),
            'branch_id' => $branch->id,
            'item_id' => $item->id,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'base_quantity' => $item->toBaseQuantity($quantity, $unitId),
            'reason' => self::clean($reason),
            'status' => PurchaseRequestStatus::Pending,
            'requested_by' => $user->id,
        ]);
    }

    public static function approve(PurchaseRequest $request, User $approver, ?string $note = null): void
    {
        self::ensureCanDecide($request, $approver);

        DB::transaction(function () use ($request, $approver, $note): void {
            $locked = PurchaseRequest::lockForUpdate()->findOrFail($request->id);
            self::expectRequestStatus($locked, [PurchaseRequestStatus::Pending], 'disetujui');

            $locked->update(['status' => PurchaseRequestStatus::Approved, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => self::clean($note)]);
        });

        $request->refresh();
    }

    public static function reject(PurchaseRequest $request, User $approver, string $reason): void
    {
        self::ensureCanDecide($request, $approver);

        if (blank($reason)) {
            throw new InvalidArgumentException('Tulis alasan penolakan.');
        }

        DB::transaction(function () use ($request, $approver, $reason): void {
            $locked = PurchaseRequest::lockForUpdate()->findOrFail($request->id);
            self::expectRequestStatus($locked, [PurchaseRequestStatus::Pending, PurchaseRequestStatus::Approved], 'ditolak');

            $locked->update(['status' => PurchaseRequestStatus::Rejected, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => trim($reason)]);
        });

        $request->refresh();
    }

    /** Dibatalkan oleh pengaju (atau penyetuju) selama belum dipesan. */
    public static function cancelRequest(PurchaseRequest $request, User $user): void
    {
        if ($request->requested_by !== $user->id && ! $user->can('Approve:Purchase')) {
            throw new InvalidArgumentException('Hanya pengaju atau penyetuju yang bisa membatalkan permintaan ini.');
        }

        DB::transaction(function () use ($request): void {
            $locked = PurchaseRequest::lockForUpdate()->findOrFail($request->id);
            self::expectRequestStatus($locked, [PurchaseRequestStatus::Pending, PurchaseRequestStatus::Approved], 'dibatalkan');

            $locked->update(['status' => PurchaseRequestStatus::Cancelled]);
        });

        $request->refresh();
    }

    // ---------------------------------------------------------------------
    // Pesanan
    // ---------------------------------------------------------------------

    /**
     * @param  list<array{item_id: int|string, unit_id: int|string, qty: float|int|string, unit_price: int|string|null, purchase_request_id?: int|string|null}>  $lines
     */
    public static function createOrder(int $branchId, ?int $supplierId, mixed $orderDate, array $lines, User $user, mixed $expectedAt = null, ?string $notes = null, bool $payNow = false): PurchaseOrder
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Isi minimal satu barang yang dipesan.');
        }

        $orderDate = Carbon::parse($orderDate ?? today())->startOfDay();

        if ($orderDate->isFuture()) {
            throw new InvalidArgumentException('Tanggal pesan tidak boleh di masa depan.');
        }

        return DB::transaction(function () use ($branchId, $supplierId, $orderDate, $lines, $user, $expectedAt, $notes, $payNow): PurchaseOrder {
            $branch = Branch::findOrFail($branchId);

            $order = PurchaseOrder::create([
                'number' => NumberSequence::nextPurchaseOrderNumber($branch->code, $orderDate),
                'branch_id' => $branch->id,
                'supplier_id' => $supplierId,
                'order_date' => $orderDate,
                'expected_at' => $expectedAt ? Carbon::parse($expectedAt) : null,
                'status' => PurchaseOrderStatus::Ordered,
                'notes' => self::clean($notes),
                'created_by' => $user->id,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $item = Item::with('units')->findOrFail((int) $line['item_id']);
                $quantity = (float) $line['qty'];
                $unitPrice = (int) ($line['unit_price'] ?? 0);

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("Jumlah {$item->name} harus lebih dari 0.");
                }

                if ($unitPrice < 0) {
                    throw new InvalidArgumentException("Harga {$item->name} tidak boleh minus.");
                }

                $requestId = filled($line['purchase_request_id'] ?? null) ? (int) $line['purchase_request_id'] : null;

                if ($requestId !== null) {
                    self::attachRequest($requestId, $order, $item);
                }

                $lineTotal = (int) round($quantity * $unitPrice);
                $total += $lineTotal;

                $order->lines()->create([
                    'item_id' => $item->id,
                    'unit_id' => (int) $line['unit_id'],
                    'factor_to_base' => $item->toBaseQuantity(1, (int) $line['unit_id']),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'purchase_request_id' => $requestId,
                ]);
            }

            $order->update(['total' => $total]);

            if ($payNow && $total > 0) {
                self::pay($order, $total, $user, $orderDate);
            }

            return $order->refresh();
        });
    }

    /**
     * Catat barang yang datang (bisa sebagian). Stok bertambah lewat StockLedger::receive.
     *
     * @param  list<array{line_id: int|string, qty: float|int|string|null, batch_number?: string|null, expired_at?: mixed}>  $lines
     */
    public static function receive(PurchaseOrder $order, array $lines, User $user, mixed $date = null, ?string $invoiceNo = null, ?string $notes = null): StockMovement
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => (float) ($line['qty'] ?? 0) > 0));

        if ($lines === []) {
            throw new InvalidArgumentException('Isi jumlah yang diterima minimal untuk satu barang.');
        }

        $movement = DB::transaction(function () use ($order, $lines, $user, $date, $invoiceNo, $notes): StockMovement {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->isOpen()) {
                throw new InvalidArgumentException("Pesanan {$locked->number} sudah {$locked->status->getLabel()}.");
            }

            /** @var Collection<int, PurchaseOrderLine> $orderLines */
            $orderLines = $locked->lines()->with('item')->lockForUpdate()->get()->keyBy('id');
            $ledgerLines = [];

            foreach ($lines as $line) {
                $orderLine = $orderLines->get((int) $line['line_id']) ?? throw new InvalidArgumentException('Baris pesanan tidak ditemukan.');
                $quantity = (float) $line['qty'];

                if ($quantity > $orderLine->remainingQuantity() + self::EPSILON) {
                    throw new InvalidArgumentException("Jumlah {$orderLine->item->name} melebihi sisa pesanan ({$orderLine->remainingQuantity()}).");
                }

                $ledgerLines[] = [
                    'item_id' => $orderLine->item_id,
                    'unit_id' => $orderLine->unit_id,
                    'qty' => $quantity,
                    'unit_price' => $orderLine->unit_price,
                    'batch_number' => $line['batch_number'] ?? null,
                    'expired_at' => $line['expired_at'] ?? null,
                ];

                $orderLine->update(['received_quantity' => round((float) $orderLine->received_quantity + $quantity, 3)]);
            }

            $movement = StockLedger::receive(
                $locked->branch_id,
                $ledgerLines,
                $locked->supplier_id,
                self::clean($invoiceNo),
                $date ?? today(),
                self::clean($notes) ?? 'Penerimaan pesanan '.$locked->number,
                $user,
                $locked,
            );

            self::refreshOrderStatus($locked);

            return $movement;
        });

        $order->refresh();

        return $movement;
    }

    /**
     * Batalkan penerimaan dari pesanan yang salah input (mutasi pembalik lewat StockLedger::cancel).
     * Jumlah diterima di baris pesanan dikurangi lagi, pesanan kembali terbuka bila belum lengkap.
     */
    public static function cancelReceipt(StockMovement $movement, string $reason, User $user): StockMovement
    {
        if ($movement->source_type !== (new PurchaseOrder)->getMorphClass() || $movement->source_id === null) {
            throw new InvalidArgumentException('Mutasi ini bukan penerimaan dari pesanan pembelian.');
        }

        return DB::transaction(function () use ($movement, $reason, $user): StockMovement {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($movement->source_id);

            if ($order->status === PurchaseOrderStatus::Cancelled) {
                throw new InvalidArgumentException("Pesanan {$order->number} sudah dibatalkan.");
            }

            $reversal = StockLedger::cancel($movement, $reason, $user, fromPurchasing: true);

            /** @var Collection<int, PurchaseOrderLine> $orderLines */
            $orderLines = $order->lines()->lockForUpdate()->get();

            foreach ($movement->lines()->get() as $receivedLine) {
                $left = abs((float) $receivedLine->entered_qty);

                // Kurangi dari baris pesanan yang cocok (barang & satuan sama), mulai dari baris terakhir.
                foreach ($orderLines->reverse() as $orderLine) {
                    if ($left <= self::EPSILON || $orderLine->item_id !== $receivedLine->item_id || $orderLine->unit_id !== $receivedLine->entered_unit_id) {
                        continue;
                    }

                    $take = min($left, (float) $orderLine->received_quantity);
                    $orderLine->update(['received_quantity' => round((float) $orderLine->received_quantity - $take, 3)]);
                    $left -= $take;
                }
            }

            self::refreshOrderStatus($order);

            return $reversal;
        });
    }

    /** Supplier tidak akan mengirim sisa pesanan: pesanan ditutup apa adanya. */
    public static function closeRemaining(PurchaseOrder $order, string $reason, User $user): void
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('Tulis alasan sisa pesanan tidak dikirim.');
        }

        DB::transaction(function () use ($order, $reason, $user): void {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== PurchaseOrderStatus::Partial) {
                throw new InvalidArgumentException('Hanya pesanan yang sudah diterima sebagian yang bisa ditutup. Pesanan yang belum datang sama sekali: batalkan.');
            }

            $locked->update(['status' => PurchaseOrderStatus::Received, 'cancel_reason' => 'Sisa tidak dikirim: '.trim($reason), 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
            $locked->requests()->where('status', PurchaseRequestStatus::Ordered)->update(['status' => PurchaseRequestStatus::Received]);
        });

        $order->refresh();
    }

    /** Batalkan pesanan yang belum datang sama sekali; uang yang sudah dibayar kembali ke kas, permintaan bisa dipesan ulang. */
    public static function cancelOrder(PurchaseOrder $order, string $reason, User $user): void
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('Tulis alasan pembatalan.');
        }

        DB::transaction(function () use ($order, $reason, $user): void {
            self::lockBranch($order->branch_id);
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== PurchaseOrderStatus::Ordered) {
                throw new InvalidArgumentException('Pesanan yang sudah diterima (sebagian) tidak bisa dibatalkan. Gunakan "Tutup sisa pesanan".');
            }

            if ($locked->paid_amount > 0) {
                PurchaseCashEntry::create([
                    'branch_id' => $locked->branch_id,
                    'entry_date' => today(),
                    'type' => PurchaseCashType::Refund,
                    'amount' => $locked->paid_amount,
                    'description' => 'Pesanan '.$locked->number.' dibatalkan: '.trim($reason),
                    'purchase_order_id' => $locked->id,
                    'created_by' => $user->id,
                ]);
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'paid_amount' => 0,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => trim($reason),
            ]);

            $locked->requests()->update(['status' => PurchaseRequestStatus::Approved, 'purchase_order_id' => null]);
        });

        $order->refresh();
    }

    // ---------------------------------------------------------------------
    // Kas pembelian
    // ---------------------------------------------------------------------

    public static function pay(PurchaseOrder $order, int $amount, User $user, mixed $date = null): PurchaseCashEntry
    {
        return DB::transaction(function () use ($order, $amount, $user, $date): PurchaseCashEntry {
            self::lockBranch($order->branch_id);
            $locked = PurchaseOrder::with('supplier')->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === PurchaseOrderStatus::Cancelled) {
                throw new InvalidArgumentException('Pesanan sudah dibatalkan.');
            }

            if ($amount <= 0 || $amount > $locked->unpaidAmount()) {
                throw new InvalidArgumentException('Nominal pembayaran harus antara Rp 1 dan sisa yang belum dibayar ('.Money::format($locked->unpaidAmount()).').');
            }

            self::ensureBalance($locked->branch_id, $amount);

            $entry = PurchaseCashEntry::create([
                'branch_id' => $locked->branch_id,
                'entry_date' => $date ? Carbon::parse($date) : today(),
                'type' => PurchaseCashType::Payment,
                'amount' => -$amount,
                'description' => 'Pembayaran pesanan '.$locked->number.($locked->supplier ? ' · '.$locked->supplier->name : ''),
                'purchase_order_id' => $locked->id,
                'created_by' => $user->id,
            ]);

            $locked->update(['paid_amount' => $locked->paid_amount + $amount]);
            $order->refresh();

            return $entry;
        });
    }

    public static function deposit(int $branchId, int $amount, string $description, User $user, mixed $date = null): PurchaseCashEntry
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal setoran harus lebih dari 0.');
        }

        return self::cashEntry($branchId, PurchaseCashType::Deposit, $amount, $description, $user, $date);
    }

    /** Pengeluaran tanpa pesanan, mis. ongkos kirim atau beli langsung di toko. */
    public static function expense(int $branchId, int $amount, string $description, User $user, mixed $date = null): PurchaseCashEntry
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pengeluaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($branchId, $amount, $description, $user, $date): PurchaseCashEntry {
            self::lockBranch($branchId);
            self::ensureBalance($branchId, $amount);

            return self::cashEntry($branchId, PurchaseCashType::Expense, -$amount, $description, $user, $date);
        });
    }

    /** Koreksi setoran/pengeluaran yang salah catat dengan entri pembalik (entri asli tetap ada). */
    public static function reverseCash(PurchaseCashEntry $entry, string $reason, User $user): PurchaseCashEntry
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('Tulis alasan koreksi.');
        }

        if (! in_array($entry->type, [PurchaseCashType::Deposit, PurchaseCashType::Expense], true)) {
            throw new InvalidArgumentException('Hanya setoran atau pengeluaran lain yang bisa dikoreksi di sini. Pembayaran pesanan dikembalikan lewat pembatalan pesanan.');
        }

        return DB::transaction(function () use ($entry, $reason, $user): PurchaseCashEntry {
            self::lockBranch($entry->branch_id);

            if (PurchaseCashEntry::where('reverses_entry_id', $entry->id)->exists()) {
                throw new InvalidArgumentException('Entri ini sudah dikoreksi.');
            }

            if ($entry->amount > 0) {
                self::ensureBalance($entry->branch_id, $entry->amount);
            }

            return PurchaseCashEntry::create([
                'branch_id' => $entry->branch_id,
                'entry_date' => today(),
                'type' => PurchaseCashType::Correction,
                'amount' => -$entry->amount,
                'description' => 'Koreksi: '.$entry->description.' · '.trim($reason),
                'reverses_entry_id' => $entry->id,
                'created_by' => $user->id,
            ]);
        });
    }

    public static function balance(int $branchId): int
    {
        return (int) PurchaseCashEntry::where('branch_id', $branchId)->sum('amount');
    }

    // ---------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------

    private static function ensureCanDecide(PurchaseRequest $request, User $approver): void
    {
        if (! $approver->can('Approve:Purchase')) {
            throw new InvalidArgumentException('Anda tidak punya izin menyetujui permintaan pembelian.');
        }

        if ($request->requested_by !== null && $request->requested_by === $approver->id) {
            throw new InvalidArgumentException('Permintaan tidak bisa disetujui atau ditolak oleh pengajunya sendiri.');
        }
    }

    /** @param  list<PurchaseRequestStatus>  $allowed */
    private static function expectRequestStatus(PurchaseRequest $request, array $allowed, string $action): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw new InvalidArgumentException("Permintaan {$request->number} sudah {$request->status->getLabel()}, tidak bisa {$action}.");
        }
    }

    private static function attachRequest(int $requestId, PurchaseOrder $order, Item $item): void
    {
        $request = PurchaseRequest::lockForUpdate()->findOrFail($requestId);

        if ($request->status !== PurchaseRequestStatus::Approved) {
            throw new InvalidArgumentException("Permintaan {$request->number} belum disetujui atau sudah dipesan.");
        }

        if ($request->branch_id !== $order->branch_id || $request->item_id !== $item->id) {
            throw new InvalidArgumentException("Permintaan {$request->number} tidak cocok dengan barang/cabang pesanan.");
        }

        $request->update(['status' => PurchaseRequestStatus::Ordered, 'purchase_order_id' => $order->id]);
    }

    /** Status pesanan & permintaan mengikuti jumlah diterima (juga setelah penerimaan dibatalkan). */
    private static function refreshOrderStatus(PurchaseOrder $order): void
    {
        $lines = $order->lines()->get();
        $complete = $lines->every(fn (PurchaseOrderLine $line): bool => $line->remainingQuantity() <= self::EPSILON);
        $nothing = $lines->every(fn (PurchaseOrderLine $line): bool => (float) $line->received_quantity <= self::EPSILON);

        $status = match (true) {
            $complete => PurchaseOrderStatus::Received,
            $nothing => PurchaseOrderStatus::Ordered,
            default => PurchaseOrderStatus::Partial,
        };

        // Pesanan yang sudah "ditutup sisa" tetap tertutup selama masih ada barang yang diterima.
        if ($order->status === PurchaseOrderStatus::Received && ! $complete && ! $nothing && $order->cancel_reason !== null) {
            $status = PurchaseOrderStatus::Received;
        }

        $order->update($status === PurchaseOrderStatus::Received || $order->cancel_reason === null
            ? ['status' => $status]
            : ['status' => $status, 'cancel_reason' => null, 'cancelled_by' => null, 'cancelled_at' => null]);

        $done = fn (PurchaseOrderLine $line): bool => $line->remainingQuantity() <= self::EPSILON || $status === PurchaseOrderStatus::Received;
        $doneRequestIds = $lines->filter(fn (PurchaseOrderLine $line): bool => $line->purchase_request_id !== null && $done($line))->pluck('purchase_request_id');
        $openRequestIds = $lines->filter(fn (PurchaseOrderLine $line): bool => $line->purchase_request_id !== null && ! $done($line))->pluck('purchase_request_id');

        if ($doneRequestIds->isNotEmpty()) {
            PurchaseRequest::whereIn('id', $doneRequestIds)->update(['status' => PurchaseRequestStatus::Received]);
        }

        if ($openRequestIds->isNotEmpty()) {
            PurchaseRequest::whereIn('id', $openRequestIds)->update(['status' => PurchaseRequestStatus::Ordered]);
        }
    }

    private static function cashEntry(int $branchId, PurchaseCashType $type, int $amount, string $description, User $user, mixed $date): PurchaseCashEntry
    {
        if (blank($description)) {
            throw new InvalidArgumentException('Tulis keterangan transaksi kas.');
        }

        return PurchaseCashEntry::create([
            'branch_id' => $branchId,
            'entry_date' => $date ? Carbon::parse($date) : today(),
            'type' => $type,
            'amount' => $amount,
            'description' => trim($description),
            'created_by' => $user->id,
        ]);
    }

    private static function ensureBalance(int $branchId, int $amount): void
    {
        $balance = self::balance($branchId);

        if ($amount > $balance) {
            throw new InvalidArgumentException('Saldo kas pembelian tidak cukup: saldo '.Money::format($balance).', dibutuhkan '.Money::format($amount).'. Catat setoran kas dulu.');
        }
    }

    /** Kunci baris cabang agar transaksi kas satu cabang berjalan bergiliran (saldo tidak bisa minus). */
    private static function lockBranch(int $branchId): void
    {
        Branch::whereKey($branchId)->lockForUpdate()->first();
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
