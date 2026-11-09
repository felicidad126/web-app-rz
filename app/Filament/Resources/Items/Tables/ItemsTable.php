<?php

namespace App\Filament\Resources\Items\Tables;

use App\Enums\ItemType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('sku')->label('SKU')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('type')->label('Jenis')->badge()->color('gray')->visibleFrom('md'),
                TextColumn::make('category.name')->label('Kategori')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('baseUnit.name')->label('Satuan dasar')->visibleFrom('md'),
                TextColumn::make('min_stock')->label('Stok min.')->numeric(decimalPlaces: 0)->alignEnd()->visibleFrom('lg'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(ItemType::class),
                SelectFilter::make('item_category_id')->label('Kategori')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Aktif'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
