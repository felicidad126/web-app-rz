<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Models\Item;
use App\Models\StockMovementLine;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Kartu stok: semua baris mutasi yang sudah tercatat untuk barang ini, terbaru di atas,
 * dengan saldo cabang setelah baris tersebut (urutan: waktu dicatat, lalu nomor baris).
 */
class StockCardRelationManager extends RelationManager
{
    protected static string $relationship = 'movementLines';

    protected static ?string $title = 'Kartu stok';

    protected static ?string $modelLabel = 'mutasi';

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
        $unit = $item->baseUnit->name;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->join('stock_movements as m', 'm.id', '=', 'stock_movement_lines.stock_movement_id')
                ->where('m.status', StockMovementStatus::Posted->value)
                ->whereIn('m.branch_id', $branches->keys())
                ->select('stock_movement_lines.*')
                ->selectSub(fn (QueryBuilder $sub) => $sub
                    ->from('stock_movement_lines as l2')
                    ->join('stock_movements as m2', 'm2.id', '=', 'l2.stock_movement_id')
                    ->whereColumn('l2.item_id', 'stock_movement_lines.item_id')
                    ->whereColumn('m2.branch_id', 'm.branch_id')
                    ->where('m2.status', StockMovementStatus::Posted->value)
                    ->where(fn (QueryBuilder $w) => $w
                        ->whereColumn('m2.posted_at', '<', 'm.posted_at')
                        ->orWhere(fn (QueryBuilder $same) => $same->whereColumn('m2.posted_at', 'm.posted_at')->whereColumn('l2.id', '<=', 'stock_movement_lines.id')))
                    ->selectRaw('coalesce(sum(l2.qty), 0)'), 'balance_after')
                ->with(['movement.branch', 'batch']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('m.posted_at')->orderByDesc('stock_movement_lines.id'))
            ->recordUrl(fn (StockMovementLine $record): string => StockMovementResource::getUrl('view', ['record' => $record->stock_movement_id]))
            ->columns([
                TextColumn::make('movement.movement_date')->label('Tanggal')->date('d/m/Y')
                    ->description(fn (StockMovementLine $record): string => $record->movement->number),
                TextColumn::make('movement.type')->label('Jenis')->badge()->visibleFrom('md'),
                TextColumn::make('movement.branch.name')->label('Cabang')->visible($branches->count() > 1),
                TextColumn::make('batch_label')->label('Batch')
                    ->state(fn (StockMovementLine $record): string => $record->batch?->label() ?? ($record->batch_number ?: '—'))
                    ->fontFamily('mono')
                    ->visibleFrom('md'),
                TextColumn::make('qty')->label('Masuk / keluar')->alignEnd()
                    ->formatStateUsing(fn ($state): string => ((float) $state < 0 ? '−' : '+').StockLedger::format(abs((float) $state)).' '.$unit),
                TextColumn::make('balance_after')->label('Saldo cabang')->alignEnd()
                    ->formatStateUsing(fn ($state): string => StockLedger::format((float) $state).' '.$unit),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(StockMovementType::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('m.type', $data['value']) : $query),
                SelectFilter::make('branch')->label('Cabang')->options($branches->all())->visible($branches->count() > 1)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('m.branch_id', $data['value']) : $query),
            ]);
    }
}
