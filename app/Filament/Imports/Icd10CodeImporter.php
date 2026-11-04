<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Icd10Code;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

/** Import daftar kode (mis. dari file resmi Kemenkes). Kode yang sudah ada akan di-update. */
class Icd10CodeImporter extends Importer
{
    use IndonesianImportNotification;

    protected static ?string $model = Icd10Code::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Kode')
                ->guess(['kode'])
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(trim($state)))
                ->rules(['required', 'max:10']),
            ImportColumn::make('name')
                ->label('Nama')
                ->guess(['nama', 'description', 'deskripsi'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('name_id')
                ->label('Nama (Indonesia)')
                ->guess(['nama_indonesia', 'nama_id'])
                ->rules(['nullable', 'max:255']),
        ];
    }

    public function resolveRecord(): Icd10Code
    {
        return Icd10Code::firstOrNew(['code' => $this->data['code']]);
    }
}
