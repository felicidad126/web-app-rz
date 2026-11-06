<?php

namespace App\Filament\Clusters\Stock\Resources\StockMovements;

use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Clusters\Stock\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Clusters\Stock\StockCluster;
use App\Models\StockMovement;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Mutasi & penerimaan';

    protected static ?string $modelLabel = 'mutasi stok';

    protected static ?string $pluralModelLabel = 'mutasi stok';

    protected static ?string $slug = 'mutasi';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', StockMovementStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'supplier', 'counterpartBranch', 'creator'])->withCount('lines'))
            ->columns([
                TextColumn::make('number')->label('No.')->fontFamily('mono')->searchable()
                    ->description(fn (StockMovement $record): string => $record->movement_date->format('d/m/Y')),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('detail')->label('Keterangan')->wrap()
                    ->state(fn (StockMovement $record): string => self::detail($record)),
                TextColumn::make('lines_count')->label('Baris')->alignCenter()->visibleFrom('lg'),
                TextColumn::make('total_cost')->label('Nilai')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state, false))
                    ->color(fn (int $state): ?string => $state < 0 ? 'gray' : null),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(StockMovementType::class)->multiple(),
                SelectFilter::make('status')->label('Status')->options(StockMovementStatus::class),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('movement_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('movement_date', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function detail(StockMovement $movement): string
    {
        return trim(match ($movement->type) {
            StockMovementType::Receipt => ($movement->supplier->name ?? 'Tanpa supplier').($movement->supplier_invoice_no ? ' · faktur '.$movement->supplier_invoice_no : ''),
            StockMovementType::TransferOut => 'Ke '.$movement->counterpartBranch?->name,
            StockMovementType::TransferIn => 'Dari '.$movement->counterpartBranch?->name,
            StockMovementType::Disposal, StockMovementType::Adjustment => (string) $movement->reason?->getLabel(),
            default => '',
        }.($movement->notes ? ' · '.$movement->notes : ''), ' ·');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'view' => ViewStockMovement::route('/{record}'),
        ];
    }
}
