<?php

namespace App\Filament\Resources\TreatmentPlans\Schemas;

use App\Enums\TreatmentPlanItemStatus;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Clinic\SurfacesField;
use App\Filament\Support\MoneyInput;
use App\Models\Treatment;
use App\Support\BranchContext;
use App\Support\Teeth;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TreatmentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Rencana')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        PatientSelect::make()->columnSpanFull(),
                        TextInput::make('title')->label('Judul')->placeholder('Mis. Rencana perawatan menyeluruh')->required()->maxLength(255),
                        Select::make('practitioner_id')
                            ->label('Dokter')
                            ->options(fn (): array => PractitionerOptions::dentists())
                            ->default(fn (): ?int => auth()->user()?->practitioner?->id)
                            ->required(),
                        Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
                        TextInput::make('visit_id')->hidden(),
                        Textarea::make('notes')->label('Catatan untuk pasien')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Item rencana')
                    ->description('Kelompokkan per fase. Untuk pilihan alternatif (mis. implan atau jembatan), buat rencana terpisah.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => SurfacesField::splitForFill($data))
                            ->orderColumn('sequence')
                            ->defaultItems(1)
                            ->addActionLabel('Tambah item')
                            ->columns(12)
                            ->live()
                            ->schema([
                                TextInput::make('phase')->label('Fase')->numeric()->minValue(1)->default(1)->required()->columnSpan(1),
                                Select::make('treatment_id')
                                    ->label('Tindakan')
                                    ->options(fn (): array => Treatment::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $treatment = $state ? Treatment::with('branchPrices')->find($state) : null;
                                        $set('price_estimate', $treatment?->priceForBranch($get('../../branch_id')));
                                    })
                                    ->required()
                                    ->columnSpan(4),
                                Select::make('tooth_number')->label('Gigi')->options(Teeth::numberOptions())->searchable()->placeholder('—')->columnSpan(2),
                                SurfacesField::make()->columnSpan(3),
                                MoneyInput::make('price_estimate')->label('Estimasi')->minValue(0)->required()->columnSpan(2),
                                // Item baru selalu "Direncanakan"; status hanya relevan saat mengubah rencana.
                                Select::make('status')->label('Status')->options(TreatmentPlanItemStatus::class)->default(TreatmentPlanItemStatus::Planned)->required()->columnSpan(3)
                                    ->disableOptionWhen(fn (string $value): bool => $value === TreatmentPlanItemStatus::Done->value)
                                    ->hiddenOn('create'),
                                TextInput::make('notes')->label('Catatan')->maxLength(255)
                                    ->columnSpan(fn (string $operation): int => $operation === 'create' ? 12 : 9),
                            ]),
                        Text::make(fn (Get $get): string => 'Total estimasi: Rp '.number_format(
                            collect($get('items') ?? [])
                                ->reject(fn (array $item): bool => ($item['status'] ?? null) === TreatmentPlanItemStatus::Cancelled->value)
                                // Isian rupiah bermasker ("400.000"); ambil angkanya saja sebelum dijumlah.
                                ->sum(fn (array $item): int => (int) preg_replace('/\D/', '', (string) ($item['price_estimate'] ?? 0))),
                            0, ',', '.')),
                    ]),
            ]);
    }
}
