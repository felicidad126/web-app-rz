<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Batch per cabang (hanya baca). Stok berubah lewat menu Stok, bukan dari sini. */
class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Batch';

    protected static ?string $modelLabel = 'batch';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('View:Stock') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Item $item */
        $item = $this->getOwnerRecord()->loadMissing('baseUnit');
        /** @var User $user */
        $user = auth()->user();
        $branches = BranchContext::available($user)->pluck('name', 'id');
        $warn = (int) config('clinic.stock.expiry_warning_days', 60);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('branch')->whereIn('branch_id', $branches->keys()))
            ->defaultSort(fn (Builder $query) => $query->orderBy('branch_id')->orderByRaw('expired_at is null')->orderBy('expired_at'))
            ->columns([
                TextColumn::make('branch.name')->label('Cabang')->visible($branches->count() > 1),
                TextColumn::make('batch_number')->label('Batch')
                    ->state(fn (ItemBatch $record): string => $record->is_unbatched ? 'Tanpa batch' : ($record->batch_number ?: '—'))
                    ->fontFamily('mono'),
                TextColumn::make('expired_at')->label('Kedaluwarsa')->date('d/m/Y')->placeholder('—')
                    ->description(fn (ItemBatch $record): ?string => match (true) {
                        $record->expired_at === null => null,
                        $record->isExpired() => 'sudah lewat',
                        ($record->daysToExpiry() ?? PHP_INT_MAX) <= $warn => $record->daysToExpiry().' hari lagi',
                        default => null,
                    })
                    ->color(fn (ItemBatch $record): ?string => match (true) {
                        $record->isExpired() => 'danger',
                        ($record->daysToExpiry() ?? PHP_INT_MAX) <= $warn => 'warning',
                        default => null,
                    }),
                TextColumn::make('qty_on_hand')->label('Stok')->alignEnd()
                    ->formatStateUsing(fn ($state): string => StockLedger::format((float) $state).' '.$item->baseUnit->name)
                    ->color(fn ($state): ?string => (float) $state < 0 ? 'danger' : null),
                TextColumn::make('unit_cost')->label('Harga pokok')->alignEnd()
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
                TextColumn::make('value')->label('Nilai')->alignEnd()
                    ->state(fn (ItemBatch $record): string => Money::format((int) round((float) $record->qty_on_hand * $record->unit_cost), false)),
                TextColumn::make('received_at')->label('Diterima')->date('d/m/Y')->placeholder('—')->visibleFrom('md'),
            ])
            ->filters([
                Filter::make('in_stock')->label('Hanya yang berstok')->default()
                    ->query(fn (Builder $query) => $query->where('qty_on_hand', '!=', 0)),
                SelectFilter::make('branch_id')->label('Cabang')->options($branches->all())->visible($branches->count() > 1),
            ]);
    }
}
