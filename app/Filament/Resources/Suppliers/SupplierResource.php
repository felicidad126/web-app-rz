<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Filament\Support\NavGroup;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SupplierResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::INVENTORY;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'supplier';

    protected static ?string $modelLabel = 'supplier';

    protected static ?string $pluralModelLabel = 'supplier & lab';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                Toggle::make('is_lab')
                    ->label('Lab gigi')
                    ->helperText('Muncul sebagai pilihan lab di order lab.')
                    ->inline(false),
                TextInput::make('contact_person')->label('Kontak')->maxLength(255),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                Textarea::make('address')->label('Alamat')->rows(2)->columnSpanFull(),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('is_lab')->label('Jenis')->badge()->color('gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Lab gigi' : 'Supplier')
                    ->visibleFrom('md'),
                TextColumn::make('contact_person')->label('Kontak')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('phone')->label('Telepon')->placeholder('—')->visibleFrom('md'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_lab')->label('Lab gigi'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([DeleteAction::make(), RestoreAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
