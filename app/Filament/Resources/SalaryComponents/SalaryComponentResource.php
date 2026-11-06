<?php

namespace App\Filament\Resources\SalaryComponents;

use App\Enums\SalaryComponentKind;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\SalaryComponents\Pages\ManageSalaryComponents;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavGroup;
use App\Models\SalaryComponent;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SalaryComponentResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = SalaryComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'komponen-gaji';

    protected static ?string $modelLabel = 'komponen gaji';

    protected static ?string $pluralModelLabel = 'komponen gaji';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(80),
            Select::make('kind')->label('Jenis')->options(SalaryComponentKind::class)->required(),
            MoneyInput::make('default_amount')->label('Nominal bawaan')->minValue(0)->default(0)
                ->helperText('Dipakai bila karyawan belum punya nominal sendiri untuk komponen ini.'),
            TextInput::make('sort')->label('Urutan')->numeric()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('kind')->label('Jenis')->badge()->visibleFrom('md'),
                TextColumn::make('default_amount')->label('Nominal bawaan')->alignEnd()
                    ->formatStateUsing(fn ($state): string => (int) $state === 0 ? '—' : Money::format((int) $state, false)),
                IconColumn::make('is_active')->label('Aktif')->boolean()->visibleFrom('md'),
            ])
            ->filters([TrashedFilter::make()])
            // Tabel pengaturan yang jarang diubah: aksi dikumpulkan di menu ⋮ supaya muat di HP.
            ->recordActions([ActionGroup::make([EditAction::make(), DeleteAction::make()->label('Arsipkan'), RestoreAction::make()])->color('gray')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSalaryComponents::route('/')];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
