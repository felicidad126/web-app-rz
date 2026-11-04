<?php

namespace App\Filament\Imports;

use App\Enums\ItemType;
use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Item;
use App\Models\Unit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Validation\Rule;

/** Import master obat & bahan. Baris dengan SKU yang sama akan di-update. */
class ItemImporter extends Importer
{
    use IndonesianImportNotification;

    protected static ?string $model = Item::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU')
                ->guess(['kode'])
                ->requiredMapping()
                ->rules(['required', 'max:30']),
            ImportColumn::make('name')
                ->label('Nama')
                ->guess(['nama', 'nama_barang'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('type')
                ->label('Jenis')
                ->helperText('medicine, material, tool, atau retail.')
                ->guess(['jenis'])
                ->castStateUsing(fn (?string $state): string => self::parseType($state))
                ->rules([Rule::enum(ItemType::class)]),
            ImportColumn::make('kfa_code')
                ->label('Kode KFA')
                ->guess(['kfa'])
                ->rules(['nullable', 'max:30']),
            ImportColumn::make('base_unit')
                ->label('Satuan dasar')
                ->helperText('Nama satuan. Dibuat otomatis jika belum ada.')
                ->guess(['satuan'])
                ->requiredMapping()
                ->rules(['required', 'max:30'])
                ->fillRecordUsing(function (Item $record, string $state): void {
                    $record->base_unit_id = Unit::firstOrCreate(['name' => strtolower(trim($state))])->getKey();
                }),
            ImportColumn::make('min_stock')
                ->label('Stok minimum')
                ->guess(['stok_min', 'minimum'])
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('sell_price')
                ->label('Harga jual')
                ->guess(['harga', 'harga_jual'])
                ->castStateUsing(fn (?string $state): ?int => blank($state) ? null : (int) preg_replace('/\D+/', '', $state))
                ->rules(['nullable', 'integer', 'min:0']),
        ];
    }

    public function resolveRecord(): Item
    {
        return Item::withTrashed()->firstOrNew(['sku' => $this->data['sku']]);
    }

    public static function parseType(?string $state): string
    {
        $normalized = strtolower(trim((string) $state));

        return match ($normalized) {
            '', 'obat', 'medicine' => ItemType::Medicine->value,
            'bahan', 'material', 'bahan dental' => ItemType::Material->value,
            'alat', 'tool', 'alat habis pakai' => ItemType::Tool->value,
            'retail', 'produk', 'produk retail' => ItemType::Retail->value,
            default => $normalized,
        };
    }
}
