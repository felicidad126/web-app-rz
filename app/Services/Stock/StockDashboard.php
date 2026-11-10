<?php

namespace App\Services\Stock;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockOpnameStatus;
use App\Models\ItemBatch;
use App\Models\PurchaseCashEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka Dashboard edisi inventori (Konsep UI tahap 2). Definisi stok mengikuti StockAlerts dan halaman Stok,
 * jadi angka di Dashboard selalu sama dengan halaman Stok. Semua nilai rupiah = harga pokok.
 */
final class StockDashboard
{
    /** Jenis mutasi yang dihitung sebagai pemakaian. */
    public const USAGE_TYPES = [StockMovementType::Usage, StockMovementType::Dispensing, StockMovementType::Sale];

    /** Kategori terbesar yang ditampilkan di donat; sisanya digabung "Lainnya". */
    public const MAX_CATEGORIES = 6;

    /** Nilai persediaan cabang (sama dengan subjudul halaman Stok › Barang). */
    public static function inventoryValue(int $branchId): int
    {
        return (int) ItemBatch::query()->where('branch_id', $branchId)->where('qty_on_hand', '>', 0)
            ->selectRaw('sum(qty_on_hand * unit_cost) as v')->value('v');
    }

    /** Jumlah barang yang punya stok di cabang. */
    public static function itemsInStock(int $branchId): int
    {
        return count(array_filter(StockAlerts::stockByItem($branchId), fn (float $qty): bool => $qty > 0));
    }

    /**
     * Pemakaian & penerimaan per minggu (8 minggu, Senin–Minggu) atau per bulan (6 bulan), nilai harga pokok.
     * Hanya mutasi yang sudah tercatat (posted); mutasi yang sudah dibatalkan tidak dihitung.
     *
     * @return list<array{label: string, start: CarbonImmutable, usage: int, receipt: int, current: bool}>
     */
    public static function flow(int $branchId, string $period = 'weeks', ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $byMonth = $period === 'months';
        $count = $byMonth ? 6 : 8;
        $current = $byMonth ? $today->startOfMonth() : $today->startOfWeek(CarbonImmutable::MONDAY);

        $buckets = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $byMonth ? $current->subMonthsNoOverflow($i) : $current->subWeeks($i);
            $buckets[$start->toDateString()] = [
                'label' => $byMonth ? $start->locale('id')->translatedFormat('M Y') : $start->locale('id')->translatedFormat('j M'),
                'start' => $start,
                'usage' => 0,
                'receipt' => 0,
                'current' => $i === 0,
            ];
        }

        $first = array_key_first($buckets);
        $types = [...array_map(fn (StockMovementType $t): string => $t->value, self::USAGE_TYPES), StockMovementType::Receipt->value];

        $rows = DB::table('stock_movement_lines as l')
            ->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->where('m.branch_id', $branchId)
            ->where('m.status', StockMovementStatus::Posted->value)
            ->whereIn('m.type', $types)
            ->whereDate('m.movement_date', '>=', $first)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as r')->whereColumn('r.reverses_movement_id', 'm.id'))
            ->groupBy('m.movement_date', 'm.type')
            ->selectRaw('m.movement_date as day, m.type as type, sum(l.line_cost) as cost')
            ->get();

        foreach ($rows as $row) {
            $day = CarbonImmutable::parse((string) $row->day);
            $key = ($byMonth ? $day->startOfMonth() : $day->startOfWeek(CarbonImmutable::MONDAY))->toDateString();

            if (! isset($buckets[$key])) {
                continue;
            }

            $field = $row->type === StockMovementType::Receipt->value ? 'receipt' : 'usage';
            $buckets[$key][$field] += abs((int) $row->cost);
        }

        return array_values($buckets);
    }

    /**
     * Nilai persediaan per kategori, terbesar dulu. Lebih dari MAX_CATEGORIES + 1 kategori: sisanya jadi "Lainnya".
     * Jumlah semua nilai = inventoryValue().
     *
     * @return list<array{label: string, value: int}>
     */
    public static function valueByCategory(int $branchId): array
    {
        $rows = DB::table('item_batches as b')
            ->join('items as i', 'i.id', '=', 'b.item_id')
            ->leftJoin('item_categories as c', 'c.id', '=', 'i.item_category_id')
            ->where('b.branch_id', $branchId)->where('b.qty_on_hand', '>', 0)
            ->groupBy('c.name')
            ->selectRaw('c.name as name, sum(b.qty_on_hand * b.unit_cost) as v')
            ->get()
            ->map(fn ($row): array => ['label' => (string) ($row->name ?? 'Tanpa kategori'), 'value' => (int) round((float) $row->v)])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        if ($rows->count() <= self::MAX_CATEGORIES + 1) {
            return $rows->all();
        }

        return [
            ...$rows->take(self::MAX_CATEGORIES)->all(),
            ['label' => 'Lainnya', 'value' => (int) $rows->slice(self::MAX_CATEGORIES)->sum('value')],
        ];
    }

    /**
     * Ringkasan alur pembelian yang masih terbuka, dengan satu contoh per kotak.
     *
     * @return array{pending: int, approved: int, open: int, late: int, pendingExample: ?PurchaseRequest, approvedExample: ?PurchaseRequest, openExample: ?PurchaseOrder}
     */
    public static function purchasing(int $branchId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $requests = fn (PurchaseRequestStatus $status) => PurchaseRequest::query()->with(['item' => fn ($query) => $query->withTrashed(), 'unit'])
            ->where('branch_id', $branchId)->where('status', $status)->oldest('id');
        $orders = PurchaseOrder::query()->with('supplier')->where('branch_id', $branchId)
            ->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial]);

        return [
            'pending' => $requests(PurchaseRequestStatus::Pending)->count(),
            'approved' => $requests(PurchaseRequestStatus::Approved)->count(),
            'open' => (clone $orders)->count(),
            'late' => (clone $orders)->whereDate('expected_at', '<', $today)->count(),
            'pendingExample' => $requests(PurchaseRequestStatus::Pending)->first(),
            'approvedExample' => $requests(PurchaseRequestStatus::Approved)->first(),
            'openExample' => (clone $orders)->orderByRaw('expected_at is null')->orderBy('expected_at')->first(),
        ];
    }

    /**
     * Saldo kas pembelian di akhir tiap minggu (8 minggu terakhir, minggu berjalan = saldo sekarang).
     *
     * @return list<int>
     */
    public static function cashBalanceWeekly(int $branchId, int $weeks = 8, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $thisWeek = $today->startOfWeek(CarbonImmutable::MONDAY);
        $entries = PurchaseCashEntry::query()->where('branch_id', $branchId)->get(['entry_date', 'amount']);

        $balances = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $end = $thisWeek->subWeeks($i)->endOfWeek(CarbonImmutable::SUNDAY);
            $balances[] = (int) $entries->filter(fn (PurchaseCashEntry $e): bool => $e->entry_date->lte($end))->sum('amount');
        }

        return $balances;
    }

    /** Uang keluar dari kas pembelian dalam $days hari terakhir (pembayaran pesanan & pengeluaran lain). */
    public static function cashOut(int $branchId, int $days = 30, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();

        return (int) abs((int) PurchaseCashEntry::query()->where('branch_id', $branchId)->where('amount', '<', 0)
            ->whereDate('entry_date', '>=', $today->subDays($days - 1))->sum('amount'));
    }

    /** Barang di bawah minimum yang sudah punya permintaan terbuka. @return array<int, PurchaseRequest> item_id → permintaan */
    public static function openRequestsByItem(int $branchId): array
    {
        return PurchaseRequest::query()->where('branch_id', $branchId)->whereIn('status', PurchaseRequestStatus::open())
            ->oldest('id')->get()->keyBy('item_id')->all();
    }

    /** Pemusnahan & opname yang menunggu persetujuan. @return array{disposals: int, opnames: int} */
    public static function pendingStockApprovals(int $branchId): array
    {
        return [
            'disposals' => StockMovement::query()->where('branch_id', $branchId)->where('status', StockMovementStatus::Pending)->count(),
            'opnames' => StockOpname::query()->where('branch_id', $branchId)->where('status', StockOpnameStatus::Pending)->count(),
        ];
    }

    /**
     * Daftar "Perlu tindakan", paling mendesak dulu. Item persetujuan hanya untuk penyetuju; pesanan yang
     * belum datang hanya untuk yang bisa menerima barang.
     *
     * @return Collection<int, array{tone: string, kind: string, title: string, meta: string, code: ?string, badge: string, id: ?int}>
     */
    public static function actionItems(int $branchId, User $user, ?CarbonImmutable $today = null): Collection
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = (int) config('clinic.stock.expiry_warning_days', 60);
        $items = collect();

        $expiring = StockAlerts::expiring($branchId, $days);
        foreach ($expiring->filter(fn (ItemBatch $b): bool => $b->isExpired()) as $batch) {
            $items->push(['tone' => 'danger', 'kind' => 'expired', 'title' => (string) $batch->item->name,
                'meta' => 'ED '.$batch->expired_at?->format('d/m/Y').' · '.StockLedger::format((float) $batch->qty_on_hand).' '.$batch->item->baseUnit?->name,
                'code' => $batch->batch_number, 'badge' => 'Lewat ED', 'id' => $batch->item_id]);
        }

        foreach (StockAlerts::negative($branchId) as $batch) {
            $items->push(['tone' => 'danger', 'kind' => 'negative', 'title' => (string) $batch->item->name,
                'meta' => 'Stok tercatat '.StockLedger::format((float) $batch->qty_on_hand).' '.$batch->item->baseUnit?->name.' · cek saat opname',
                'code' => null, 'badge' => 'Stok minus', 'id' => $batch->item_id]);
        }

        if ($user->can('Approve:Stock')) {
            $pending = self::pendingStockApprovals($branchId);
            if ($pending['disposals'] > 0) {
                $items->push(['tone' => 'warning', 'kind' => 'approve-disposal', 'title' => 'Pemusnahan menunggu persetujuan',
                    'meta' => $pending['disposals'].' mutasi · disetujui owner/admin, bukan pengaju', 'code' => null, 'badge' => 'Setujui', 'id' => null]);
            }
            if ($pending['opnames'] > 0) {
                $items->push(['tone' => 'warning', 'kind' => 'approve-opname', 'title' => 'Opname menunggu persetujuan',
                    'meta' => $pending['opnames'].' opname · selisih dicatat sebagai koreksi', 'code' => null, 'badge' => 'Setujui', 'id' => null]);
            }
        }

        if ($user->can('Approve:Purchase')) {
            $requests = PurchaseRequest::query()->with(['item' => fn ($query) => $query->withTrashed(), 'unit'])->where('branch_id', $branchId)
                ->where('status', PurchaseRequestStatus::Pending)->oldest('id')->get();
            foreach ($requests as $request) {
                $items->push(['tone' => 'warning', 'kind' => 'approve-request', 'title' => (string) $request->item->name,
                    'meta' => 'Permintaan '.StockLedger::format((float) $request->quantity).' '.$request->unit->name.' menunggu persetujuan',
                    'code' => $request->number, 'badge' => 'Setujui', 'id' => $request->id]);
            }
        }

        $orders = PurchaseOrder::query()->with('supplier')->where('branch_id', $branchId)
            ->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial])->orderBy('expected_at')->get();
        foreach ($orders->filter(fn (PurchaseOrder $o): bool => $o->expected_at !== null && $o->expected_at->lt($today)) as $order) {
            $items->push(['tone' => 'warning', 'kind' => 'late-order', 'title' => 'Pesanan '.$order->supplier?->name,
                'meta' => 'Perkiraan datang '.$order->expected_at?->format('d/m/Y').($order->status === PurchaseOrderStatus::Partial ? ' · baru datang sebagian' : ''),
                'code' => $order->number, 'badge' => 'Lewat perkiraan', 'id' => $order->id]);
        }

        $open = self::openRequestsByItem($branchId);
        $stock = StockAlerts::stockByItem($branchId);
        foreach (StockAlerts::belowMinimum($branchId) as $item) {
            $request = $open[$item->id] ?? null;
            $items->push(['tone' => 'warning', 'kind' => 'below-minimum', 'title' => $item->name,
                'meta' => StockLedger::format($stock[$item->id] ?? 0.0).' / min. '.StockLedger::format((float) $item->min_stock).' '.$item->baseUnit?->name
                    .($request ? ' · sudah diminta' : ' · belum diminta'),
                'code' => $request?->number, 'badge' => 'Di bawah min.', 'id' => $request?->id]);
        }

        foreach ($expiring->reject(fn (ItemBatch $b): bool => $b->isExpired()) as $batch) {
            $left = (int) $today->diffInDays($batch->expired_at, false);
            $items->push(['tone' => 'warning', 'kind' => 'expiring', 'title' => (string) $batch->item->name,
                'meta' => 'ED '.$batch->expired_at?->format('d/m/Y').' · '.StockLedger::format((float) $batch->qty_on_hand).' '.$batch->item->baseUnit?->name,
                'code' => $batch->batch_number, 'badge' => 'ED '.$left.' hari', 'id' => $batch->item_id]);
        }

        if ($user->can('Receive:Purchase')) {
            foreach ($orders->reject(fn (PurchaseOrder $o): bool => $o->expected_at !== null && $o->expected_at->lt($today)) as $order) {
                $items->push(['tone' => 'gray', 'kind' => 'incoming-order', 'title' => 'Pesanan '.$order->supplier?->name,
                    'meta' => ($order->expected_at ? 'Perkiraan datang '.$order->expected_at->format('d/m/Y') : 'Belum ada perkiraan datang').' · terima saat barang datang',
                    'code' => $order->number, 'badge' => 'Belum datang', 'id' => $order->id]);
            }
        }

        return $items->values();
    }
}
