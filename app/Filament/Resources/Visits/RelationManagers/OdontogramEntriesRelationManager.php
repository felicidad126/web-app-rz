<?php

namespace App\Filament\Resources\Visits\RelationManagers;

use App\Enums\OdontogramEntryType;
use App\Enums\OdontogramScope;
use App\Enums\ToothSurface;
use App\Filament\Clinic\Concerns\EditsOdontogramChart;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\Teeth;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class OdontogramEntriesRelationManager extends ClinicalRelationManager
{
    use EditsOdontogramChart;

    protected static string $relationship = 'odontogramEntries';

    protected static ?string $title = 'Odontogram';

    protected static ?string $modelLabel = 'catatan odontogram';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::fields());
    }

    /** @return array<int, mixed> */
    public static function fields(): array
    {
        return [
            Select::make('tooth_number')
                ->label('Gigi')
                ->options(Teeth::groupedOptions())
                ->searchable()
                ->required()
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                    if (! Teeth::isValid((string) $value)) {
                        $fail('Nomor gigi FDI tidak valid.');
                    }
                }),
            Select::make('condition_code')
                ->label('Kondisi / kode')
                ->options(fn (): array => OdontogramCondition::query()->where('is_active', true)->orderBy('sort')->get()
                    ->mapWithKeys(fn (OdontogramCondition $c): array => [$c->code => $c->code.' · '.$c->name])->all())
                ->searchable()
                ->live()
                ->required(),
            ToggleButtons::make('surface')
                ->label('Permukaan')
                ->options(collect(ToothSurface::cases())->mapWithKeys(fn (ToothSurface $s): array => [$s->value => $s->value])->all())
                ->inline()
                ->helperText(fn (Get $get): string => self::scope($get('condition_code')) === OdontogramScope::Tooth
                    ? 'Kode ini berlaku untuk seluruh gigi: permukaan dikosongkan.'
                    : 'M mesial · O oklusal/insisal · D distal · V bukal/labial · L lingual/palatal')
                ->disabled(fn (Get $get): bool => self::scope($get('condition_code')) === OdontogramScope::Tooth)
                ->required(fn (Get $get): bool => self::scope($get('condition_code')) === OdontogramScope::Surface),
            ToggleButtons::make('entry_type')
                ->label('Jenis catatan')
                ->options(OdontogramEntryType::class)
                ->default(OdontogramEntryType::Existing)
                ->inline()
                ->required(),
            TextInput::make('notes')->label('Catatan')->maxLength(255)->columnSpanFull(),
        ];
    }

    private static function scope(?string $code): ?OdontogramScope
    {
        return $code ? OdontogramCondition::where('code', $code)->value('applies_to') : null;
    }

    protected function odontogramPatient(): Patient
    {
        return Patient::query()->findOrFail($this->getOwnerRecord()->getAttribute('patient_id'));
    }

    protected function odontogramVisit(): ?Visit
    {
        /** @var Visit $visit */
        $visit = $this->getOwnerRecord();

        return $visit;
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(fn (): View => view('clinic.odontogram-chart', [
                'chart' => $this->odontogramChartData(),
                'editable' => ! $this->isReadOnly(),
                'withVisit' => true,
            ]))
            ->description('Catatan baru tidak menimpa riwayat. Status terkini per gigi dihitung dari catatan terakhir. Bisa juga dicatat lewat tombol "Tambah catatan".')
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('condition'))
            ->columns([
                TextColumn::make('tooth_number')->label('Gigi')->sortable(),
                TextColumn::make('surface')->label('Permukaan')->placeholder('Seluruh gigi'),
                TextColumn::make('condition_code')
                    ->label('Kondisi')
                    ->formatStateUsing(fn (OdontogramEntry $record): string => $record->condition_code.' · '.$record->condition->name),
                TextColumn::make('entry_type')->label('Jenis')->badge(),
                TextColumn::make('notes')->label('Catatan')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah catatan')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'patient_id' => $this->getOwnerRecord()->getAttribute('patient_id'),
                        'surface' => self::scope($data['condition_code'] ?? null) === OdontogramScope::Tooth ? null : ($data['surface'] ?? null),
                    ]),
                Action::make('chart')
                    ->label('Lihat odontogram lengkap')
                    ->color('gray')
                    ->url(function (): string {
                        /** @var Visit $visit */
                        $visit = $this->getOwnerRecord();

                        return PatientResource::getUrl('odontogram', ['record' => $visit->patient_id]);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
