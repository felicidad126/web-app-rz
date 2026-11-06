<?php

namespace App\Filament\Resources\Treatments\RelationManagers;

use App\Filament\Clusters\Stock\StockActions;
use App\Models\Item;
use App\Models\Treatment;
use App\Models\TreatmentMaterial;
use App\Services\Stock\StockAlerts;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * BOM: bahan untuk 1× tindakan. Dipotong otomatis (FEFO) saat rekam medis ditandatangani;
 * perkiraan HPP dihitung dari harga pokok rata-rata di cabang aktif.
 */
class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    protected static ?string $title = 'Bahan per tindakan';

    protected static ?string $modelLabel = 'bahan';

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Barang')
                    ->options(fn (): array => Item::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => $set('unit_id', Item::find($state)?->base_unit_id))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('treatment_id', $this->getOwnerRecord()->getKey()))
                    ->validationMessages(['unique' => 'Barang ini sudah ada di daftar bahan tindakan ini.']),
                TextInput::make('qty')
                    ->label('Jumlah per tindakan')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(),
                Select::make('unit_id')
                    ->label('Satuan')
                    ->options(fn (Get $get): array => StockActions::unitOptions($get('item_id')))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Treatment $treatment */
        $treatment = $this->getOwnerRecord();
        $branchId = BranchContext::id();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.baseUnit', 'item.units', 'unit']))
            ->description(fn (): string => 'Dipotong otomatis dari stok (batch terdekat kedaluwarsa) saat rekam medis ditandatangani. '
                .'Perkiraan HPP bahan per tindakan: '.Money::format(StockAlerts::estimatedMaterialCost($treatment->fresh() ?? $treatment, $branchId)).'.')
            ->columns([
                TextColumn::make('item.name')->label('Barang'),
                TextColumn::make('qty')->label('Jumlah')->alignEnd()
                    ->formatStateUsing(fn (TreatmentMaterial $record): string => StockLedger::format((float) $record->qty).' '.$record->unit->name)
                    ->description(fn (TreatmentMaterial $record): ?string => $record->unit_id === $record->item->base_unit_id
                        ? null
                        : '= '.StockLedger::format($record->baseQty()).' '.$record->item->baseUnit->name),
                TextColumn::make('cost')->label('Perkiraan HPP')->alignEnd()
                    ->state(fn (TreatmentMaterial $record): string => Money::format((int) round($record->baseQty() * StockLedger::averageCost($record->item_id, $branchId)), false)),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah bahan')->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
