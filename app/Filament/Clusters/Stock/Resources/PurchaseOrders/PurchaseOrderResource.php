<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Clusters\Stock\StockCluster;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Models\PurchaseOrder;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tab "Pesanan": pesanan ke supplier, penerimaan (bisa sebagian), dan pembayaran dari kas pembelian. */
class PurchaseOrderResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'pesanan pembelian';

    protected static ?string $pluralModelLabel = 'pesanan pembelian';

    protected static ?string $slug = 'pesanan';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    /** Pesanan tidak diubah setelah dibuat: batalkan (belum datang) atau tutup sisanya. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('branch_id', BranchContext::id())
            ->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial])
            ->whereDate('expected_at', '<', today())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pesanan lewat tanggal perkiraan datang';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'supplier', 'lines.item'])->withCount('lines'))
            ->recordUrl(fn (PurchaseOrder $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada pesanan')
            ->emptyStateDescription('Buat pesanan dari permintaan yang sudah disetujui (tab Permintaan) atau langsung lewat "Buat pesanan".')
            ->columns([
                TextColumn::make('number')->label('No.')->fontFamily('mono')->searchable()
                    ->description(fn (PurchaseOrder $record): string => $record->order_date->format('d/m/Y')),
                TextColumn::make('supplier.name')->label('Supplier')->placeholder('Tanpa supplier')->searchable()->wrap()
                    ->description(fn (PurchaseOrder $record): string => self::itemsSummary($record)),
                TextColumn::make('total')->label('Total')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state, false))
                    ->description(fn (PurchaseOrder $record): string => self::paymentLabel($record))
                    ->visibleFrom('sm'),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (PurchaseOrder $record): ?string => self::arrivalLabel($record)),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable(),
            ]);
    }

    public static function itemsSummary(PurchaseOrder $order): string
    {
        $names = $order->lines->map(fn ($line): string => $line->item->name);

        return $names->take(2)->implode(', ').($names->count() > 2 ? ' +'.($names->count() - 2).' lainnya' : '');
    }

    public static function paymentLabel(PurchaseOrder $order): string
    {
        return match (true) {
            $order->status === PurchaseOrderStatus::Cancelled => 'Batal',
            $order->total === 0 => '',
            $order->unpaidAmount() === 0 => 'Lunas',
            $order->paid_amount === 0 => 'Belum dibayar',
            default => 'Kurang '.Money::format($order->unpaidAmount(), false),
        };
    }

    /** "Datang 15/11" atau "lewat 3 hari" untuk pesanan yang masih terbuka. */
    public static function arrivalLabel(PurchaseOrder $order): ?string
    {
        if (! $order->status->isOpen() || $order->expected_at === null) {
            return null;
        }

        $late = (int) $order->expected_at->startOfDay()->diffInDays(today(), false);

        return $late > 0 ? 'Lewat '.$late.' hari' : 'Perkiraan '.$order->expected_at->format('d/m');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/buat'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
