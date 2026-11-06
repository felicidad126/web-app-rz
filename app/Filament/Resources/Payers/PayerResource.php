<?php

namespace App\Filament\Resources\Payers;

use App\Enums\PayerType;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Payers\Pages\ManagePayers;
use App\Filament\Support\NavGroup;
use App\Models\Payer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PayerResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Payer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORGANIZATION;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'penjamin';

    protected static ?string $pluralModelLabel = 'penjamin';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Jenis')
                    ->options(PayerType::class)
                    ->required(),
                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('phone')->label('Telepon')->placeholder('-')->visibleFrom('md'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(PayerType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
