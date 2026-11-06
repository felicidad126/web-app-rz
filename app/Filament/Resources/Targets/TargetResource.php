<?php

namespace App\Filament\Resources\Targets;

use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Targets\Pages\ManageTargets;
use App\Filament\Support\NavGroup;
use App\Models\Target;
use App\Services\Reports\Targets;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Target produksi bulanan per cabang, atau per dokter di cabang. */
class TargetResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Target::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::REPORTS;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'target';

    protected static ?string $modelLabel = 'target';

    protected static ?string $pluralModelLabel = 'target produksi';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')->label('Bulan')->options(fn (): array => self::monthOptions())
                ->default(now()->startOfMonth()->toDateString())->required(),
            Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id())->required(),
            Select::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists())->placeholder('Seluruh cabang')
                ->helperText('Kosongkan untuk target cabang.')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                    ->where('period', $get('period'))->where('branch_id', $get('branch_id'))->where('metric', 'production'))
                ->validationMessages(['unique' => 'Target untuk dokter ini di bulan & cabang itu sudah ada.']),
            TextInput::make('amount')->label('Target produksi sebulan')->prefix('Rp')->numeric()->minValue(1)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'practitioner.user']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('period')->orderBy('branch_id')->orderBy('practitioner_id'))
            ->columns([
                TextColumn::make('period')->label('Bulan')->formatStateUsing(fn (Target $record): string => $record->period->locale('id')->translatedFormat('F Y')),
                TextColumn::make('branch.name')->label('Cabang'),
                TextColumn::make('practitioner.user.name')->label('Dokter')->placeholder('Seluruh cabang'),
                TextColumn::make('amount')->label('Target')->alignEnd()->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
                TextColumn::make('working_days')->label('Hari kerja')->alignEnd()
                    ->state(fn (Target $record): int => Targets::workingDays($record->period, $record->branch_id, $record->practitioner_id)->count()),
                TextColumn::make('daily')->label('Target harian')->alignEnd()
                    ->state(function (Target $record): string {
                        $days = Targets::workingDays($record->period, $record->branch_id, $record->practitioner_id)->count();

                        return $days > 0 ? Money::format((int) round($record->amount / $days), false) : 'Tidak ada jadwal praktik';
                    }),
            ])
            ->filters([SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())])
            ->recordActions([EditAction::make()]);
    }

    /** @return array<string, string> */
    public static function monthOptions(): array
    {
        return collect(range(-2, 11))->map(fn (int $i): Carbon => now()->startOfMonth()->subMonths($i))
            ->mapWithKeys(fn (Carbon $c): array => [$c->toDateString() => $c->locale('id')->translatedFormat('F Y')])->all();
    }

    public static function getPages(): array
    {
        return ['index' => ManageTargets::route('/')];
    }
}
