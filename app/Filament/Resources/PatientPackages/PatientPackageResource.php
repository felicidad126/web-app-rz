<?php

namespace App\Filament\Resources\PatientPackages;

use App\Enums\InstallmentStatus;
use App\Enums\PatientPackageStatus;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\PatientPackages\Pages\CreatePatientPackage;
use App\Filament\Resources\PatientPackages\Pages\ListPatientPackages;
use App\Filament\Resources\PatientPackages\Pages\ViewPatientPackage;
use App\Filament\Support\NavGroup;
use App\Models\Installment;
use App\Models\Package;
use App\Models\PatientPackage;
use App\Services\Billing\PackageSales;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/** Paket & cicilan pasien (mockup "Paket & cicilan"). */
class PatientPackageResource extends Resource
{
    protected static ?string $model = PatientPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::BILLING;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'paket pasien';

    protected static ?string $pluralModelLabel = 'paket & cicilan';

    protected static ?string $navigationLabel = 'Paket & cicilan';

    protected static ?string $slug = 'paket-cicilan';

    protected static ?string $recordTitleAttribute = 'package_number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Paket')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        PatientSelect::make()->default(fn (): ?int => request()->integer('patient') ?: null)->columnSpanFull(),
                        Select::make('package_id')
                            ->label('Paket')
                            ->options(fn (): array => Package::where('is_active', true)->orderBy('name')->get()
                                ->mapWithKeys(fn (Package $p): array => [$p->id => $p->name.' · '.Money::format($p->price)])->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('price', Package::find($state)?->price)),
                        Select::make('practitioner_id')->label('Dokter penanggung jawab')->options(fn (): array => PractitionerOptions::dentists())->searchable(),
                        Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id())->required(),
                        DatePicker::make('start_date')->label('Tanggal mulai (DP)')->native(false)->displayFormat('d/m/Y')->default(today())->required()->live(),
                    ]),
                Section::make('Pembayaran')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('price')->label('Harga paket')->prefix('Rp')->numeric()->minValue(1)->required()->live(onBlur: true),
                        TextInput::make('down_payment')->label('Uang muka (DP)')->prefix('Rp')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true),
                        TextInput::make('installment_count')->label('Jumlah cicilan')->numeric()->minValue(0)->maxValue(60)->default(6)->required()->live(onBlur: true)
                            ->helperText('0 = bayar lunas di awal (DP = harga paket).'),
                        TextInput::make('due_day')->label('Jatuh tempo tiap tanggal')->numeric()->minValue(1)->maxValue(28)->default(fn (): int => min(28, today()->day))->required()->live(onBlur: true),
                        Text::make(fn (Get $get): HtmlString => self::schedulePreview($get))->columnSpanFull(),
                        Textarea::make('notes')->label('Catatan perjanjian')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'installments']))
            ->columns([
                TextColumn::make('package_number')->label('No. paket')->fontFamily('mono')->searchable()
                    ->description(fn (PatientPackage $record): string => $record->start_date->format('d/m/Y')),
                TextColumn::make('patient.name')->label('Pasien')->searchable(['name', 'rm_number'])
                    ->description(fn (PatientPackage $record): string => $record->patient->rm_number),
                TextColumn::make('name')->label('Paket')->visibleFrom('md'),
                TextColumn::make('price')->label('Harga')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false))->visibleFrom('lg'),
                TextColumn::make('remaining')->label('Sisa')->alignEnd()
                    ->state(fn (PatientPackage $record): int => $record->remainingAmount())
                    ->formatStateUsing(fn (int $state): string => Money::format($state, false)),
                TextColumn::make('next_due')->label('Cicilan berikutnya')
                    ->state(fn (PatientPackage $record): ?string => self::nextDue($record)?->due_date->format('d/m/Y'))
                    ->description(fn (PatientPackage $record): ?string => self::nextDue($record)?->label())
                    ->color(fn (PatientPackage $record): ?string => self::nextDue($record)?->isOverdue() ? 'danger' : null)
                    ->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(PatientPackageStatus::class)->default(PatientPackageStatus::Active->value),
                Filter::make('overdue')->label('Ada cicilan lewat jatuh tempo')
                    ->query(fn (Builder $query) => $query->whereHas('installments', fn (Builder $q) => $q
                        ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Billed])
                        ->whereDate('due_date', '<', today()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function nextDue(PatientPackage $package): ?Installment
    {
        return $package->installments
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Billed])
            ->sortBy('sequence')
            ->first();
    }

    private static function schedulePreview(Get $get): HtmlString
    {
        try {
            $rows = PackageSales::schedule(
                (int) $get('price'),
                (int) $get('down_payment'),
                (int) $get('installment_count'),
                Carbon::parse($get('start_date') ?? today()),
                max(1, min(28, (int) $get('due_day'))),
            );
        } catch (Throwable) {
            return new HtmlString('');
        }

        if ($rows === []) {
            return new HtmlString('<span style="color:#52525b">Isi harga, DP, dan jumlah cicilan untuk melihat jadwal.</span>');
        }

        $items = array_map(fn (array $row): string => '<li>'.e(($row['sequence'] === 0 ? 'DP' : 'Ke-'.$row['sequence']).' · '.$row['due_date']->format('d/m/Y').' · '.Money::format($row['amount'])).'</li>', $rows);

        return new HtmlString('<div style="font-size:13px"><div style="font-weight:600;margin-bottom:4px">Jadwal pembayaran</div><ul style="margin:0;padding-left:18px;columns:2">'.implode('', $items).'</ul></div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientPackages::route('/'),
            'create' => CreatePatientPackage::route('/create'),
            'view' => ViewPatientPackage::route('/{record}'),
        ];
    }
}
