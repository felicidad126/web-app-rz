<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('sku')
                            ->label('Kode / SKU')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Jenis')
                            ->options(ItemType::class)
                            ->required(),
                        Select::make('item_category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                            ->createOptionForm([
                                TextInput::make('name')->label('Nama kategori')->required()->maxLength(80)->unique('item_categories', 'name'),
                            ])
                            ->preload(),
                        TextInput::make('kfa_code')
                            ->label('Kode KFA')
                            ->helperText('Kamus Farmasi & Alkes Kemenkes. Wajib untuk obat yang diresepkan (SATUSEHAT).')
                            ->maxLength(30),
                        Select::make('base_unit_id')
                            ->label('Satuan dasar')
                            ->helperText('Satuan terkecil untuk pencatatan stok. Konversi satuan lain diatur setelah disimpan.')
                            ->relationship('baseUnit', 'name')
                            ->createOptionForm([
                                TextInput::make('name')->label('Nama satuan')->required()->maxLength(30)->unique('units', 'name'),
                            ])
                            ->preload()
                            ->required(),
                        TextInput::make('min_stock')
                            ->label('Stok minimum')
                            ->helperText('Dalam satuan dasar. Dipakai untuk peringatan stok menipis.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        MoneyInput::make('sell_price')
                            ->label('Harga jual')
                            ->helperText('Per satuan dasar. Kosongkan jika tidak dijual terpisah.')
                            ->minValue(0),
                        Toggle::make('track_expiry')
                            ->label('Lacak tanggal kedaluwarsa')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}
