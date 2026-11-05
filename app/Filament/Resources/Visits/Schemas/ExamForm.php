<?php

namespace App\Filament\Resources\Visits\Schemas;

use App\Enums\DiagnosisType;
use App\Enums\ItemType;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Clinic\SurfacesField;
use App\Models\Icd10Code;
use App\Models\Item;
use App\Models\Treatment;
use App\Models\TreatmentPlanItem;
use App\Models\Visit;
use App\Support\Teeth;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Form pemeriksaan satu kunjungan: SOAP, tanda vital, diagnosis ICD-10, tindakan ICD-9-CM, resep.
 */
class ExamForm
{
    public static function configure(Schema $schema, Visit $visit): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.clinic.patient-banner')->viewData(['patient' => $visit->patient]),
                Group::make()
                    ->relationship('medicalRecord')
                    ->schema([
                        Section::make('Anamnesis & pemeriksaan')
                            ->columns(2)
                            ->schema([
                                Textarea::make('subjective')->label('Keluhan & anamnesis (S)')->rows(3)->required(),
                                Textarea::make('objective')->label('Pemeriksaan klinis (O)')->rows(3),
                                Grid::make(4)->columnSpanFull()->schema([
                                    TextInput::make('vitals.blood_pressure')->label('Tekanan darah')->placeholder('120/80')->suffix('mmHg')->maxLength(7),
                                    TextInput::make('vitals.pulse')->label('Nadi')->numeric()->minValue(20)->maxValue(250)->suffix('/menit'),
                                    TextInput::make('vitals.temperature')->label('Suhu')->numeric()->minValue(30)->maxValue(45)->step(0.1)->suffix('°C'),
                                    TextInput::make('vitals.respiratory_rate')->label('Napas')->numeric()->minValue(5)->maxValue(60)->suffix('/menit'),
                                ]),
                                Textarea::make('assessment')->label('Penilaian (A)')->rows(2),
                                Textarea::make('plan')->label('Rencana (P)')->rows(2),
                            ]),
                        Section::make('Diagnosis (ICD-10)')
                            ->description('Wajib minimal satu sebelum rekam medis ditandatangani.')
                            ->schema([
                                Repeater::make('diagnoses')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->addActionLabel('Tambah diagnosis')
                                    ->columns(12)
                                    ->schema([
                                        Select::make('icd10_code')
                                            ->label('Diagnosis')
                                            ->searchable()
                                            ->getSearchResultsUsing(fn (string $search): array => self::searchIcd10($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => Icd10Code::where('code', $value)->first()?->label)
                                            ->required()
                                            ->columnSpan(6),
                                        Select::make('type')->label('Jenis')->options(DiagnosisType::class)->default(DiagnosisType::Primary)->required()->columnSpan(2),
                                        Select::make('tooth_number')->label('Gigi')->options(Teeth::numberOptions())->searchable()->columnSpan(2),
                                        TextInput::make('notes')->label('Catatan')->maxLength(255)->columnSpan(2),
                                    ]),
                            ]),
                    ]),
                Section::make('Tindakan (ICD-9-CM)')
                    ->description('Tarif dikunci (snapshot) saat tindakan disimpan.')
                    ->schema([
                        Repeater::make('procedures')
                            ->hiddenLabel()
                            ->relationship()
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => SurfacesField::splitForFill($data))
                            ->defaultItems(0)
                            ->addActionLabel('Tambah tindakan')
                            ->columns(12)
                            ->schema([
                                Select::make('treatment_plan_item_id')
                                    ->label('Dari rencana perawatan')
                                    ->options(fn (): array => self::openPlanItems($visit))
                                    ->placeholder('Bukan dari rencana')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if ($item = TreatmentPlanItem::find($state)) {
                                            $set('treatment_id', $item->treatment_id);
                                            $set('tooth_number', $item->tooth_number);
                                            $set('surfaces', str_split((string) $item->surfaces) ?: []);
                                        }
                                    })
                                    ->columnSpan(12)
                                    ->visible(fn (): bool => self::openPlanItems($visit) !== []),
                                Select::make('treatment_id')
                                    ->label('Tindakan')
                                    ->options(fn (): array => self::treatmentOptions($visit))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(5),
                                Select::make('tooth_number')->label('Gigi')->options(Teeth::numberOptions())->searchable()->columnSpan(2),
                                SurfacesField::make()->columnSpan(3),
                                TextInput::make('qty')->label('Jumlah')->numeric()->minValue(1)->default(1)->required()->columnSpan(2),
                                Select::make('operator_id')
                                    ->label('Operator')
                                    ->options(fn (): array => PractitionerOptions::dentists())
                                    ->default($visit->practitioner_id)
                                    ->required()
                                    ->columnSpan(4),
                                Select::make('assistant_id')
                                    ->label('Asisten')
                                    ->options(fn (): array => PractitionerOptions::all())
                                    ->columnSpan(4),
                                Text::make(fn (Get $get): string => self::priceHint($visit, $get('treatment_id'), $get('price_snapshot')))
                                    ->columnSpan(4),
                                TextInput::make('price_snapshot')->hidden()->dehydrated(false),
                            ]),
                    ]),
                Group::make()
                    ->relationship('prescription', condition: fn (?array $state): bool => filled($state['items'] ?? null))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'practitioner_id' => $visit->practitioner_id])
                    ->schema([
                        Section::make('Resep')
                            ->schema([
                                Repeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->addActionLabel('Tambah obat')
                                    ->columns(12)
                                    ->schema([
                                        Select::make('item_id')
                                            ->label('Obat')
                                            ->options(fn (): array => Item::query()->where('type', ItemType::Medicine)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->live()
                                            ->required()
                                            ->columnSpan(5),
                                        TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.5)->required()->columnSpan(2),
                                        Select::make('unit_id')
                                            ->label('Satuan')
                                            ->options(fn (Get $get): array => self::unitOptions($get('item_id')))
                                            ->required()
                                            ->columnSpan(2),
                                        TextInput::make('signa')->label('Aturan pakai')->placeholder('3×1 sesudah makan')->required()->maxLength(255)->columnSpan(3),
                                    ]),
                                Textarea::make('notes')->label('Catatan resep')->rows(2),
                            ]),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    public static function searchIcd10(string $search): array
    {
        return Icd10Code::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('code', 'like', "{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('name_id', 'like', "%{$search}%"))
            ->orderBy('code')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Icd10Code $c): array => [$c->code => $c->label])
            ->all();
    }

    /** @return array<int, string> */
    private static function treatmentOptions(Visit $visit): array
    {
        return Treatment::query()
            ->where('is_active', true)
            ->with('branchPrices')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Treatment $t): array => [
                $t->id => $t->name.' · Rp '.number_format($t->priceForBranch($visit->branch_id), 0, ',', '.'),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function openPlanItems(Visit $visit): array
    {
        return TreatmentPlanItem::query()
            ->where('status', TreatmentPlanItemStatus::Planned)
            ->whereHas('plan', fn ($q) => $q->where('patient_id', $visit->patient_id)
                ->whereIn('status', [TreatmentPlanStatus::Accepted, TreatmentPlanStatus::PartiallyDone]))
            ->with('treatment')
            ->orderBy('phase')
            ->orderBy('sequence')
            ->get()
            ->mapWithKeys(fn (TreatmentPlanItem $item): array => [$item->id => $item->label()])
            ->all();
    }

    private static function priceHint(Visit $visit, mixed $treatmentId, mixed $snapshot): string
    {
        if (filled($snapshot)) {
            return 'Tarif tersimpan: Rp '.number_format((int) $snapshot, 0, ',', '.');
        }

        $treatment = $treatmentId ? Treatment::with('branchPrices')->find($treatmentId) : null;

        return $treatment ? 'Tarif saat ini: Rp '.number_format($treatment->priceForBranch($visit->branch_id), 0, ',', '.') : '';
    }

    /** @return array<int, string> */
    private static function unitOptions(mixed $itemId): array
    {
        $item = $itemId ? Item::with(['baseUnit', 'units.unit'])->find($itemId) : null;

        if (! $item) {
            return [];
        }

        return collect([$item->base_unit_id => $item->baseUnit->name])
            ->union($item->units->mapWithKeys(fn ($u): array => [$u->unit_id => $u->unit->name.' ('.(float) $u->factor_to_base.' '.$item->baseUnit->name.')']))
            ->all();
    }
}
