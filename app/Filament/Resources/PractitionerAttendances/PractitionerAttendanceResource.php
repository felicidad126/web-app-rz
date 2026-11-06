<?php

namespace App\Filament\Resources\PractitionerAttendances;

use App\Enums\AttendanceType;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\PractitionerAttendances\Pages\ManagePractitionerAttendances;
use App\Filament\Support\NavGroup;
use App\Models\Practitioner;
use App\Models\PractitionerAttendance;
use App\Services\Commission\Attendances;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Kehadiran dokter yang dibayar: uang hadir & on call (keputusan owner 6 Nov 2026). */
class PractitionerAttendanceResource extends Resource
{
    protected static ?string $model = PractitionerAttendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'kehadiran-dokter';

    protected static ?string $modelLabel = 'kehadiran';

    protected static ?string $pluralModelLabel = 'kehadiran dokter';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    public static function canEdit(Model $record): bool
    {
        /** @var PractitionerAttendance $record */
        return $record->commission_payout_id === null && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists())->searchable()->required()
                    ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::fillAmount($get, $set))
                    ->disabledOn('edit'),
                Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id())->required()->disabledOn('edit'),
                DatePicker::make('date')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->default(today())->maxDate(today()->addDay())->required()->disabledOn('edit'),
                Select::make('type')->label('Jenis')->options(AttendanceType::class)->default(AttendanceType::Present)->required()
                    ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::fillAmount($get, $set))
                    ->disabledOn('edit'),
                TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->minValue(0)->required()
                    ->helperText('Terisi dari tarif dokter; bisa diubah untuk hari tertentu.'),
                TextInput::make('notes')->label('Catatan')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => Money::format((int) $state, false);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['practitioner.user', 'branch', 'payout']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('practitioner.user.name')->label('Dokter')->searchable(),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('md'),
                TextColumn::make('type')->label('Jenis')->badge()->color('gray'),
                TextColumn::make('amount')->label('Nominal')->alignEnd()->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('payout')->label('Rekap')->placeholder('Belum direkap')
                    ->state(fn (PractitionerAttendance $record): ?string => $record->payout?->periodLabel()),
                TextColumn::make('notes')->label('Catatan')->placeholder('—')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists()),
                SelectFilter::make('type')->label('Jenis')->options(AttendanceType::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    private static function fillAmount(Get $get, Set $set): void
    {
        $practitioner = Practitioner::find($get('practitioner_id'));
        $type = $get('type');

        if ($practitioner !== null && $type !== null) {
            $set('amount', Attendances::defaultAmount($practitioner, $type instanceof AttendanceType ? $type : AttendanceType::from($type)));
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePractitionerAttendances::route('/'),
        ];
    }
}
