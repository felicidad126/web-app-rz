<?php

namespace App\Filament\Resources\Payslips;

use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Filament\Resources\Payslips\Pages\EditPayslip;
use App\Filament\Resources\Payslips\Pages\ListPayslips;
use App\Filament\Support\NavGroup;
use App\Models\Payslip;
use App\Support\Money;
use BackedEnum;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class PayslipResource extends Resource
{
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
                            TableColumn::make('Jenis')->width('22%'),
                            TableColumn::make('Komponen')->width('48%'),
                            TableColumn::make('Nominal')->width('30%'),
                        ])
                        ->schema([
                            Select::make('kind')->options(SalaryComponentKind::class)->required(),
                            TextInput::make('name')->required()->maxLength(80),
                            TextInput::make('amount')->numeric()->minValue(0)->prefix('Rp')->required(),
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
                TextColumn::make('user.name')->label('Karyawan')->searchable()
                    ->description(fn (Payslip $record): string => $record->periodLabel()),
                TextColumn::make('earning_total')->label('Pendapatan')->alignEnd()->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('deduction_total')->label('Potongan')->alignEnd()->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('net_total')->label('Diterima')->alignEnd()->weight('bold')->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('period')->label('Periode')
                    ->options(fn (): array => self::periodOptions())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereDate('period', $data['value']) : $query),
                SelectFilter::make('status')->label('Status')->options(PayoutStatus::class),
            ])
            ->recordActions([EditAction::make()->label(fn (Payslip $record): string => $record->isDraft() ? 'Ubah' : 'Lihat')]);
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
