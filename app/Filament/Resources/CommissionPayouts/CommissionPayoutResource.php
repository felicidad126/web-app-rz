<?php

namespace App\Filament\Resources\CommissionPayouts;

use App\Enums\PayoutStatus;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\CommissionPayouts\Pages\ListCommissionPayouts;
use App\Filament\Resources\CommissionPayouts\Pages\ViewCommissionPayout;
use App\Filament\Support\NavGroup;
use App\Models\CommissionPayout;
use App\Support\CommissionScope;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
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

/** Rekap jasa dokter per bulan (mockup "Komisi dokter"). */
class CommissionPayoutResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = CommissionPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'jasa-dokter';

    protected static ?string $modelLabel = 'rekap jasa';

    protected static ?string $pluralModelLabel = 'rekap jasa dokter';

    protected static ?string $navigationLabel = 'Rekap jasa dokter';

    public static function getEloquentQuery(): Builder
    {
        return CommissionScope::apply(parent::getEloquentQuery());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => Money::format((int) $state, false);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('practitioner.user')
                ->withCount(['commissions as procedure_count' => fn (Builder $q) => $q->whereNull('reverses_commission_id')])
                ->withSum('commissions as gross_sum', 'gross_amount')
                ->withSum('commissions as discount_sum', 'discount_amount')
                ->withSum('commissions as lab_sum', 'lab_cost')
                ->withSum('commissions as base_sum', 'base_amount'))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('period')->orderBy('practitioner_id'))
            ->recordUrl(fn (CommissionPayout $record): string => self::getUrl('view', ['record' => $record]))
            ->columns([
                // Bulan cukup di filter Periode; ditulis per baris hanya bila semua periode ditampilkan.
                TextColumn::make('practitioner.user.name')->label('Dokter / nakes')
                    ->description(fn (CommissionPayout $record, HasTable $livewire): ?string => self::filteredPeriod($livewire) ? null : $record->periodLabel()),
                TextColumn::make('procedure_count')->label('Tindakan')->alignEnd()->visibleFrom('md'),
                // Area isi di layar 1440 px hanya ±1050 px (ada sidebar): rincian produksi baru tampil di layar sangat lebar.
                TextColumn::make('gross_sum')->label('Produksi bruto')->alignEnd()->formatStateUsing($money)->visibleFrom('2xl'),
                TextColumn::make('discount_sum')->label('Diskon')->alignEnd()->formatStateUsing($money)->visibleFrom('2xl'),
                TextColumn::make('lab_sum')->label('Biaya lab')->alignEnd()->formatStateUsing($money)->visibleFrom('2xl'),
                TextColumn::make('base_sum')->label('Basis')->alignEnd()->formatStateUsing($money)->visibleFrom('lg'),
                TextColumn::make('rate')->label('Tarif')->alignEnd()->visibleFrom('2xl')
                    ->state(fn (CommissionPayout $record): string => ((int) $record->getAttribute('base_sum')) > 0
                        ? number_format($record->commission_total / (int) $record->getAttribute('base_sum') * 100, 1, ',', '.').'%' : '—'),
                TextColumn::make('commission_total')->label('Jasa')->alignEnd()->formatStateUsing($money)->visibleFrom('sm')
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('allowance_total')->label('Hadir / on call')->alignEnd()->formatStateUsing($money)->visibleFrom('md')
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('adjustment_total')->label('Tambahan / potongan')->alignEnd()->formatStateUsing($money)->visibleFrom('xl'),
                TextColumn::make('total')->label('Diterima')->alignEnd()->weight('bold')->formatStateUsing($money)
                    ->summarize(Sum::make()->label('')->formatStateUsing($money)),
                TextColumn::make('status')->label('Status')->badge()->extraAttributes(['class' => 'klinik-status']),
            ])
            ->filters([
                SelectFilter::make('period')->label('Periode')
                    ->options(fn (): array => self::periodOptions())
                    ->default(fn (): ?string => self::latestPeriod())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereDate('period', $data['value']) : $query),
                SelectFilter::make('status')->label('Status')->options(PayoutStatus::class),
            ])
            // Filter Periode selalu terlihat dan langsung berlaku (tanpa tombol "Terapkan" kedua yang berwarna teal).
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->filtersResetAction(fn (Action $action): Action => $action->color('gray'))
            // Baris bisa diklik; di HP tautan "Rincian" disembunyikan supaya kolom Diterima & Status muat.
            ->recordActions([ViewAction::make()->label('Rincian')->extraAttributes(['class' => 'klinik-hide-sm'])]);
    }

    /** Periode rekap terbaru yang boleh dilihat pengguna (default filter Periode). */
    public static function latestPeriod(): ?string
    {
        $period = CommissionScope::apply(CommissionPayout::query())->max('period');

        return $period ? Carbon::parse($period)->toDateString() : null;
    }

    public static function filteredPeriod(HasTable $livewire): bool
    {
        return filled($livewire->getTableFilterState('period')['value'] ?? null);
    }

    /** @return array<string, string> 12 bulan terakhir + bulan yang sudah punya rekap */
    public static function periodOptions(): array
    {
        $months = collect(range(0, 11))->map(fn (int $i): Carbon => now()->startOfMonth()->subMonths($i));
        $existing = CommissionPayout::query()->distinct()->pluck('period')->map(fn ($p): Carbon => Carbon::parse($p)->startOfMonth());

        return $months->merge($existing)->unique(fn (Carbon $c): string => $c->toDateString())->sortDesc()
            ->mapWithKeys(fn (Carbon $c): array => [$c->toDateString() => $c->locale('id')->translatedFormat('F Y')])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionPayouts::route('/'),
            'view' => ViewCommissionPayout::route('/{record}'),
        ];
    }
}
