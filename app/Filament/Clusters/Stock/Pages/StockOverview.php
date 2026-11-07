<?php

namespace App\Filament\Clusters\Stock\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Clusters\Stock\StockCluster;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Services\Stock\StockAlerts;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Tab "Barang" (mockup Stok): stok per barang di cabang aktif, batch terdekat & ED, harga pokok, perlu tindakan.
 */
class StockOverview extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.stock.overview';

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Barang';

    protected static ?string $title = 'Stok';

    protected static ?string $slug = 'barang';

    /** semua | minimum | kedaluwarsa | minus */
    #[Url(as: 'tampil')]
    public string $view_filter = 'semua';

    /** @var array<int, float>|null */
    private ?array $stock = null;

    /** @var array<int, array{batch: ItemBatch|null, cost: int}>|null */
    private ?array $details = null;

    /** @var array<int, string>|null barang yang sudah diminta/dipesan (belum datang): item_id => keterangan */
    private ?array $purchasing = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:Stock') ?? false;
    }

    /** Judul "Stok" + tab sudah menunjukkan posisi; breadcrumb hanya menambah baris. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        $branchId = BranchContext::id();

        if ($branchId === null) {
            return 'Anda belum ditugaskan ke cabang mana pun.';
        }

        $branch = BranchContext::available()->firstWhere('id', $branchId)?->name;
        $value = (int) ItemBatch::query()->where('branch_id', $branchId)->where('qty_on_hand', '>', 0)->selectRaw('sum(qty_on_hand * unit_cost) as v')->value('v');

        return $branch.' · '.count(array_filter($this->stock())).' barang berstok · nilai persediaan '.Money::format($value).' (harga pokok)';
    }

    /** @return array<string, int> jumlah per tampilan, untuk tombol filter */
    public function counts(): array
    {
        $branchId = (int) BranchContext::id();

        return [
            'semua' => Item::query()->where('is_active', true)->count(),
            'minimum' => StockAlerts::belowMinimum($branchId)->count(),
            'kedaluwarsa' => StockAlerts::expiring($branchId)->pluck('item_id')->unique()->count(),
            'minus' => StockAlerts::negative($branchId)->count(),
        ];
    }

    public function setView(string $view): void
    {
        $this->view_filter = in_array($view, ['semua', 'minimum', 'kedaluwarsa', 'minus'], true) ? $view : 'semua';
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->defaultSort('name')
            ->recordUrl(fn (Item $record): ?string => ItemResource::canEdit($record) ? ItemResource::getUrl('edit', ['record' => $record]) : null)
            ->emptyStateHeading('Tidak ada barang di tampilan ini')
            ->columns([
                TextColumn::make('name')->label('Barang')->searchable(['name', 'sku'])->weight('medium')
                    ->description(fn (Item $record): string => $record->sku),
                TextColumn::make('category.name')->label('Kategori')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('stock')->label('Stok')->alignEnd()
                    ->state(fn (Item $record): float => $this->stock()[$record->id] ?? 0.0)
                    ->formatStateUsing(fn (float $state, Item $record): string => StockLedger::format($state).' '.$record->baseUnit->name)
                    ->color(fn (float $state, Item $record): ?string => $state < 0 ? 'danger' : ($state < (float) $record->min_stock ? 'warning' : null)),
                TextColumn::make('min_stock')->label('Minimum')->alignEnd()->formatStateUsing(fn ($state): string => StockLedger::format((float) $state))->visibleFrom('md'),
                TextColumn::make('nearest')->label('Batch terdekat · kedaluwarsa')
                    ->state(fn (Item $record): ?string => $this->detail($record)['batch']?->label())
                    ->color(fn (Item $record): ?string => $this->expiryColor($this->detail($record)['batch']))
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->visibleFrom('md'),
                TextColumn::make('cost')->label('Harga pokok')->alignEnd()
                    ->state(fn (Item $record): int => $this->detail($record)['cost'])
                    ->formatStateUsing(fn (int $state): string => $state ? Money::format($state, false) : '—')
                    ->visibleFrom('lg'),
                TextColumn::make('action_needed')->label('Perlu tindakan')
                    ->state(fn (Item $record): ?string => $this->actionNeeded($record))
                    ->wrap()
                    ->color('warning')
                    ->placeholder('')
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('item_category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->recordActions([
                PurchaseActions::requestItem(forRow: true)
                    ->visible(fn (Item $record): bool => ! isset($this->purchasing()[$record->id])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            StockActions::startOpname(),
            ActionGroup::make([StockActions::usage(), StockActions::transfer(), StockActions::disposal()])
                ->label('Lainnya')->button()->color('gray'),
            StockActions::receive(),
        ];
    }

    /** @return Builder<Item> */
    private function baseQuery(): Builder
    {
        $query = Item::query()->with(['category', 'baseUnit'])->where('is_active', true);
        $branchId = (int) BranchContext::id();

        return match ($this->view_filter) {
            'minimum' => $query->whereKey(StockAlerts::belowMinimum($branchId)->pluck('id')),
            'kedaluwarsa' => $query->whereKey(StockAlerts::expiring($branchId)->pluck('item_id')),
            'minus' => $query->whereKey(StockAlerts::negative($branchId)->pluck('item_id')),
            default => $query,
        };
    }

    /** @return array<int, float> */
    private function stock(): array
    {
        return $this->stock ??= StockAlerts::stockByItem((int) BranchContext::id());
    }

    /** @return array{batch: ItemBatch|null, cost: int} */
    private function detail(Item $item): array
    {
        if ($this->details === null) {
            $branchId = (int) BranchContext::id();
            $this->details = [];

            // Batch terdekat (yang berstok) per barang + harga pokok rata-rata tertimbang, dalam satu kali baca.
            $batches = ItemBatch::query()->where('branch_id', $branchId)->where('is_unbatched', false)->where('qty_on_hand', '>', 0)
                ->orderByRaw('case when expired_at is null then 1 else 0 end')->orderBy('expired_at')->get()->groupBy('item_id');

            foreach ($batches as $itemId => $rows) {
                $qty = (float) $rows->sum('qty_on_hand');
                $this->details[(int) $itemId] = [
                    'batch' => $rows->first(),
                    'cost' => $qty > 0 ? (int) round($rows->sum(fn (ItemBatch $b): float => (float) $b->qty_on_hand * $b->unit_cost) / $qty) : 0,
                ];
            }
        }

        return $this->details[$item->id] ?? ['batch' => null, 'cost' => 0];
    }

    /** @return array<int, string> */
    private function purchasing(): array
    {
        if ($this->purchasing === null) {
            $branchId = (int) BranchContext::id();

            $requested = PurchaseRequest::query()->where('branch_id', $branchId)
                ->whereIn('status', [PurchaseRequestStatus::Pending, PurchaseRequestStatus::Approved])
                ->orderBy('id')->get(['item_id', 'number'])
                ->mapWithKeys(fn (PurchaseRequest $r): array => [$r->item_id => 'sudah diminta '.$r->number]);

            $ordered = PurchaseOrderLine::query()
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                ->where('purchase_orders.branch_id', $branchId)
                ->whereIn('purchase_orders.status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial])
                ->whereColumn('purchase_order_lines.received_quantity', '<', 'purchase_order_lines.quantity')
                ->orderBy('purchase_orders.id')
                ->get(['purchase_order_lines.item_id', 'purchase_orders.number'])
                ->mapWithKeys(fn (PurchaseOrderLine $line): array => [$line->item_id => 'dalam pesanan '.$line->getAttribute('number')]);

            $this->purchasing = $ordered->union($requested)->all();
        }

        return $this->purchasing;
    }

    private function expiryColor(?ItemBatch $batch): ?string
    {
        $days = $batch?->daysToExpiry();

        return match (true) {
            $days === null => null,
            $days < 0 => 'danger',
            $days <= (int) config('clinic.stock.expiry_warning_days', 60) => 'warning',
            default => null,
        };
    }

    private function actionNeeded(Item $item): ?string
    {
        $stock = $this->stock()[$item->id] ?? 0.0;
        $batch = $this->detail($item)['batch'];
        $days = $batch?->daysToExpiry();

        return match (true) {
            $stock < 0 => 'Stok minus · cek saat opname',
            $days !== null && $days < 0 => 'Kedaluwarsa · ajukan pemusnahan',
            $stock < (float) $item->min_stock => 'Di bawah minimum · '.($this->purchasing()[$item->id] ?? 'pesan lagi'),
            $days !== null && $days <= (int) config('clinic.stock.expiry_warning_days', 60) => 'Pakai lebih dulu (FEFO)',
            default => null,
        };
    }
}
