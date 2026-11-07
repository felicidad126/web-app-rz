<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

/**
 * Import supplier (mis. dari aplikasi inventori BHP klinik, lihat docs/13-pembelian.md).
 * Supplier dengan nama yang sama akan di-update, termasuk yang pernah diarsipkan.
 */
class SupplierImporter extends Importer
{
    use IndonesianImportNotification;

    protected static ?string $model = Supplier::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nama supplier')
                ->guess(['nama', 'nama_supplier', 'name', 'company', 'perusahaan'])
                ->requiredMapping()
                ->rules(['required', 'max:150']),
            ImportColumn::make('contact_person')
                ->label('Kontak')
                ->guess(['kontak', 'contact_person', 'sales'])
                ->rules(['nullable', 'max:100']),
            ImportColumn::make('phone')
                ->label('Telepon')
                ->guess(['telepon', 'phone', 'no_hp', 'hp', 'wa'])
                ->rules(['nullable', 'max:30']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email', 'max:150']),
            ImportColumn::make('address')
                ->label('Alamat')
                ->guess(['alamat'])
                ->rules(['nullable', 'max:1000']),
            ImportColumn::make('notes')
                ->label('Catatan')
                ->guess(['catatan', 'keterangan'])
                ->rules(['nullable', 'max:1000']),
        ];
    }

    public function resolveRecord(): Supplier
    {
        $supplier = Supplier::withTrashed()->firstOrNew(['name' => trim((string) $this->data['name'])]);

        if ($supplier->trashed()) {
            $supplier->restore();
        }

        return $supplier;
    }
}
