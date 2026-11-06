<?php

namespace App\Filament\Clusters\Stock\Resources\StockOpnames;

use App\Enums\StockOpnameStatus;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\CountStockOpname;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Clusters\Stock\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Clusters\Stock\StockCluster;
use App\Models\StockOpname;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Opname';

    protected static ?string $modelLabel = 'stok opname';

    protected static ?string $pluralModelLabel = 'stok opname';

    protected static ?string $slug = 'opname';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Hitung hanya selama status "sedang dihitung". */
    public static function canEdit(Model $record): bool
    {
        /** @var StockOpname $record */
        return $record->isCounting() && parent::canEdit($record);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'creator', 'approver'])->withCount('lines'))
            ->columns([
                TextColumn::make('number')->label('No.')->fontFamily('mono')
                    ->description(fn (StockOpname $record): string => $record->started_at->format('d/m/Y H:i')),
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('lines_count')->label('Baris')->alignCenter(),
                TextColumn::make('creator.name')->label('Dihitung oleh')->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (StockOpname $record): ?string => $record->rejected_reason && $record->isCounting() ? 'Dikembalikan: '.$record->rejected_reason : null),
                TextColumn::make('approver.name')->label('Disetujui oleh')->placeholder('—')->visibleFrom('lg'),
            ])
            ->recordUrl(fn (StockOpname $record): string => static::canEdit($record) ? static::getUrl('edit', ['record' => $record]) : static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('count')->label('Hitung')->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (StockOpname $record): bool => static::canEdit($record))
                    ->url(fn (StockOpname $record): string => static::getUrl('edit', ['record' => $record])),
                ViewAction::make()->visible(fn (StockOpname $record): bool => $record->status !== StockOpnameStatus::Counting),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOpnames::route('/'),
            'view' => ViewStockOpname::route('/{record}'),
            'edit' => CountStockOpname::route('/{record}/hitung'),
        ];
    }
}
