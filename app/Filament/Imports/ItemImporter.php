<?php

namespace App\Filament\Imports;

use App\Enums\ItemType;
use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Validation\Rule;

/**
 * Import master obat & bahan. Baris dengan SKU yang sama akan di-update.
 * Juga mengenali ekspor aplikasi inventori BHP klinik (kolom code, name, brand, kategori, unit, minimum_stock):
 * lihat docs/13-pembelian.md. Tanpa kolom jenis, barang baru dianggap bahan (BHP).
 */
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
                ->guess(['kode', 'code', 'kode_barang'])
                ->requiredMapping()
                ->rules(['required', 'max:30']),
            ImportColumn::make('name')
                ->label('Nama')
                ->guess(['nama', 'nama_barang', 'name'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('brand')
                ->label('Merek')
                ->helperText('Opsional. Ditambahkan di belakang nama, mis. "Masker (Sensi)".')
                ->guess(['merek', 'brand'])
                ->rules(['nullable', 'max:100'])
                ->fillRecordUsing(function (Item $record, ?string $state): void {
                    $brand = trim((string) $state);

                    if ($brand !== '' && ! str_contains(mb_strtolower($record->name), mb_strtolower($brand))) {
                        $record->name = mb_substr($record->name.' ('.$brand.')', 0, 255);
                    }
                }),
            ImportColumn::make('category')
                ->label('Kategori')
                ->helperText('Nama kategori. Dibuat otomatis jika belum ada.')
                ->guess(['kategori', 'category', 'nama_kategori', 'category_name'])
                ->rules(['nullable', 'max:80'])
                ->fillRecordUsing(function (Item $record, ?string $state): void {
                    if (filled($state)) {
                        $record->item_category_id = self::category($state)->getKey();
                    }
                }),
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
                ->guess(['satuan', 'unit'])
                ->requiredMapping()
                ->rules(['required', 'max:30'])
                ->fillRecordUsing(function (Item $record, string $state): void {
                    $record->base_unit_id = Unit::firstOrCreate(['name' => strtolower(trim($state))])->getKey();
                }),
            ImportColumn::make('min_stock')
                ->label('Stok minimum')
                ->guess(['stok_min', 'minimum', 'minimum_stock'])
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('sell_price')
                ->label('Harga jual')
                ->guess(['harga', 'harga_jual'])
                ->castStateUsing(fn (?string $state): ?int => blank($state) ? null : (int) preg_replace('/\D+/', '', $state))
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('is_active')
                ->label('Aktif')
                ->guess(['aktif'])
                ->boolean()
                ->rules(['nullable', 'boolean']),
        ];
    }

    /** Barang baru tanpa kolom jenis = bahan habis pakai (BHP). */
    protected function beforeSave(): void
    {
        if (blank($this->record->getAttribute('type'))) {
            $this->record->setAttribute('type', ItemType::Material);
        }
    }

    private static function category(string $name): ItemCategory
    {
        $name = trim($name);

        $category = ItemCategory::withTrashed()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($category?->trashed()) {
            $category->restore();
        }

        return $category ?? ItemCategory::create(['name' => $name]);
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
