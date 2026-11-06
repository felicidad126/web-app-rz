<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\MoneyInput;
use App\Models\SalaryComponent;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/** Komponen gaji tetap karyawan (owner). Disalin ke slip gaji bulanan. */
class SalaryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryItems';

    protected static ?string $title = 'Komponen gaji';

    protected static ?string $modelLabel = 'komponen gaji';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('ViewAny:Payslip') ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('Manage:Payroll') ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('salary_component_id')->label('Komponen')
                ->options(fn (): array => SalaryComponent::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')->all())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('user_id', $this->getOwnerRecord()->getKey()))
                ->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('amount', SalaryComponent::find($state)?->default_amount))
                ->required(),
            MoneyInput::make('amount')->label('Nominal per bulan')->minValue(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('component'))
            ->columns([
                TextColumn::make('component.name')->label('Komponen'),
                TextColumn::make('component.kind')->label('Jenis')->badge(),
                TextColumn::make('amount')->label('Nominal')->alignEnd()->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
            ])
            ->headerActions([CreateAction::make()->label('Tambah komponen')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
