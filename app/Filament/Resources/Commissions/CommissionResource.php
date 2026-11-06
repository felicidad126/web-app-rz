<?php

namespace App\Filament\Resources\Commissions;

use App\Enums\CommissionRole;
use App\Enums\CommissionStatus;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Support\NavGroup;
use App\Models\Commission;
use App\Support\BranchContext;
use App\Support\CommissionScope;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Rincian entri jasa per tindakan (hanya baca). Koreksi lewat void/refund tagihan. */
class CommissionResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'rincian-jasa';

    protected static ?string $modelLabel = 'jasa';

    protected static ?string $pluralModelLabel = 'rincian jasa';

    protected static ?string $navigationLabel = 'Rincian jasa';

    public static function getEloquentQuery(): Builder
    {
        return CommissionScope::apply(parent::getEloquentQuery())->whereIn('commissions.branch_id', BranchContext::available()->pluck('id'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => Money::format((int) $state, false);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['practitioner.user', 'invoice.patient', 'invoiceItem', 'payout']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('service_date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('service_date')->label('Tanggal')->date('d/m/Y')->sortable()
                    ->description(fn (Commission $record): string => $record->invoice->label()),
                TextColumn::make('invoice.patient.name')->label('Pasien')->searchable()->wrap(),
                TextColumn::make('invoiceItem.description')->label('Tindakan')->wrap()
                    ->description(fn (Commission $record): ?string => $record->isReversal() ? 'Pembalik: '.$record->reason : ($record->rule_snapshot['summary'] ?? null)),
                TextColumn::make('practitioner.user.name')->label('Dokter / nakes')
                    ->description(fn (Commission $record): ?string => $record->role === CommissionRole::Assistant ? 'Asisten' : null)
                    ->visible(fn (): bool => auth()->user()?->can('ViewAll:Commission') ?? false),
                TextColumn::make('gross_amount')->label('Bruto')->alignEnd()->formatStateUsing($money)->visibleFrom('lg'),
                TextColumn::make('discount_amount')->label('Diskon')->alignEnd()->formatStateUsing($money)->visibleFrom('xl'),
                TextColumn::make('lab_cost')->label('Lab')->alignEnd()->formatStateUsing($money)->visibleFrom('xl'),
                TextColumn::make('base_amount')->label('Basis')->alignEnd()->formatStateUsing($money)->visibleFrom('md'),
                TextColumn::make('amount')->label('Jasa')->alignEnd()->formatStateUsing($money)
                    ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)
                    ->summarize(Sum::make()->label('Total')->formatStateUsing($money)),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (Commission $record): ?string => $record->payout ? 'Rekap '.$record->payout->periodLabel() : null),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y')->default(now()->startOfMonth()),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y')->default(now()->endOfMonth()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('service_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('service_date', '<=', $date)))
                    ->indicateUsing(fn (array $data): ?string => ($data['from'] ?? null) ? 'Tanggal tindakan '.date('d/m/Y', strtotime($data['from'])).' – '.(($data['until'] ?? null) ? date('d/m/Y', strtotime($data['until'])) : '…') : null),
                SelectFilter::make('practitioner_id')->label('Dokter / nakes')->options(fn (): array => PractitionerOptions::all())
                    ->visible(fn (): bool => auth()->user()?->can('ViewAll:Commission') ?? false),
                SelectFilter::make('status')->label('Status')->options(CommissionStatus::class),
                SelectFilter::make('role')->label('Peran')->options(CommissionRole::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
        ];
    }
}
