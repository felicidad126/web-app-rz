<?php

namespace App\Filament\Resources\Payslips;

use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Payslips\Pages\EditPayslip;
use App\Filament\Resources\Payslips\Pages\ListPayslips;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavGroup;
use App\Models\Payslip;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class PayslipResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Payslip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'slip-gaji';

    protected static ?string $modelLabel = 'slip gaji';

    protected static ?string $pluralModelLabel = 'slip gaji staf';

    protected static ?string $navigationLabel = 'Slip gaji staf';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Baris slip')
                ->description('Komponen tetap disalin dari data karyawan. Tambahkan lembur, kasbon, atau PPh 21 (manual) di sini.')
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->relationship(modifyQueryUsing: fn ($query) => $query->orderBy('sort'))
                        ->table([
                            TableColumn::make('Jenis')->width('22%')->alignStart(),
                            TableColumn::make('Komponen')->width('48%')->alignStart(),
                            TableColumn::make('Nominal')->width('30%')->alignEnd(),
                        ])
                        // Label dipakai di HP (tabel repeater ditumpuk per baris).
                        ->schema([
                            Select::make('kind')->label('Jenis')->options(SalaryComponentKind::class)->required(),
                            TextInput::make('name')->label('Komponen')->required()->maxLength(80),
                            MoneyInput::make('amount')->label('Nominal')->minValue(0)->required(),
                        ])
                        ->orderColumn('sort')
                        ->addActionLabel('Tambah baris'),
                ]),
            Textarea::make('notes')->label('Catatan')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => Money::format((int) $state, false);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('period')->orderBy('user_id'))
            ->columns([
                // Bulan cukup di filter Periode; ditulis per baris hanya bila semua periode ditampilkan.
                TextColumn::make('user.name')->label('Karyawan')->searchable()
                    ->description(fn (Payslip $record, HasTable $livewire): ?string => filled($livewire->getTableFilterState('period')['value'] ?? null) ? null : $record->periodLabel()),
                TextColumn::make('earning_total')->label('Pendapatan')->alignEnd()->formatStateUsing($money)->visibleFrom('md')
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('deduction_total')->label('Potongan')->alignEnd()->formatStateUsing($money)->visibleFrom('md')
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('net_total')->label('Diterima')->alignEnd()->weight('bold')->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('status')->label('Status')->badge()->extraAttributes(['class' => 'klinik-status']),
            ])
            ->filters([
                SelectFilter::make('period')->label('Periode')
                    ->options(fn (): array => self::periodOptions())
                    ->default(fn (): ?string => ($period = Payslip::query()->max('period')) ? Carbon::parse($period)->toDateString() : null)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereDate('period', $data['value']) : $query),
                SelectFilter::make('status')->label('Status')->options(PayoutStatus::class),
            ])
            // Filter Periode selalu terlihat dan langsung berlaku (tanpa tombol "Terapkan" kedua yang berwarna teal).
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->filtersResetAction(fn (Action $action): Action => $action->color('gray'))
            ->recordActions([EditAction::make()
                ->extraAttributes(['class' => 'klinik-hide-sm'])
                ->label(fn (Payslip $record): string => $record->isDraft() ? 'Ubah' : 'Lihat')
                ->icon(fn (Payslip $record): Heroicon => $record->isDraft() ? Heroicon::OutlinedPencilSquare : Heroicon::OutlinedEye)]);
    }

    /** @return array<string, string> */
    public static function periodOptions(): array
    {
        return collect(range(-1, 11))->map(fn (int $i): Carbon => now()->startOfMonth()->subMonths($i))
            ->mapWithKeys(fn (Carbon $c): array => [$c->toDateString() => $c->locale('id')->translatedFormat('F Y')])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayslips::route('/'),
            'edit' => EditPayslip::route('/{record}'),
        ];
    }
}
