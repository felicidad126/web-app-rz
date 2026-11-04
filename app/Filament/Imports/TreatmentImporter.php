<?php

namespace App\Filament\Imports;

use App\Enums\TreatmentCategory;
use App\Filament\Imports\Concerns\IndonesianImportNotification;
use App\Models\Treatment;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Validation\Rule;

/** Import master tindakan & tarif. Baris dengan kode yang sama akan di-update. */
class TreatmentImporter extends Importer
{
    use IndonesianImportNotification;

    protected static ?string $model = Treatment::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Kode')
                ->guess(['kode'])
                ->requiredMapping()
                ->rules(['required', 'max:20']),
            ImportColumn::make('name')
                ->label('Nama tindakan')
                ->guess(['nama', 'tindakan'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->label('Kategori')
                ->helperText('Kode kategori: '.collect(TreatmentCategory::cases())->pluck('value')->join(', '))
                ->guess(['kategori'])
                ->castStateUsing(fn (?string $state): string => self::parseCategory($state))
                ->rules([Rule::enum(TreatmentCategory::class)]),
            ImportColumn::make('icd9cm_code')
                ->label('ICD-9-CM')
                ->guess(['icd9', 'icd_9', 'icd9cm'])
                ->rules(['nullable', 'exists:icd9cm_codes,code']),
            ImportColumn::make('base_price')
                ->label('Tarif')
                ->guess(['tarif', 'harga'])
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?int => $state === null ? null : (int) preg_replace('/\D+/', '', $state))
                ->rules(['required', 'integer', 'min:0']),
            ImportColumn::make('duration_minutes')
                ->label('Durasi (menit)')
                ->guess(['durasi'])
                ->castStateUsing(fn (?string $state): int => blank($state) ? 30 : (int) $state)
                ->rules(['integer', 'min:5', 'max:600']),
        ];
    }

    public function resolveRecord(): Treatment
    {
        return Treatment::withTrashed()->firstOrNew(['code' => $this->data['code']]);
    }

    public static function parseCategory(?string $state): string
    {
        if (blank($state)) {
            return TreatmentCategory::Other->value;
        }

        $normalized = strtolower(trim($state));

        foreach (TreatmentCategory::cases() as $case) {
            if ($normalized === $case->value || $normalized === strtolower($case->getLabel())) {
                return $case->value;
            }
        }

        return $normalized;
    }
}
