<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\Packages\Pages\ManagePackages;
use App\Filament\Support\NavGroup;
use App\Models\Package;
use App\Models\Treatment;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Master paket perawatan (mis. ortodonti): harga dan kuota tindakan. */
class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'paket';

    protected static ?string $pluralModelLabel = 'paket perawatan';

    protected static ?string $navigationLabel = 'Paket perawatan';

    protected static ?string $slug = 'paket';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('Kode')->required()->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama paket')->required()->maxLength(255),
                TextInput::make('price')->label('Harga')->prefix('Rp')->numeric()->minValue(0)->required(),
                TextInput::make('validity_days')->label('Masa berlaku')->suffix('hari')->numeric()->minValue(1)->helperText('Kosongkan bila tidak kedaluwarsa.'),
                Textarea::make('description')->label('Termasuk / tidak termasuk')->rows(2)->columnSpanFull(),
                Repeater::make('items')
                    ->label('Isi paket')
                    ->relationship()
                    ->columns(3)
                    ->minItems(1)
                    ->addActionLabel('Tambah tindakan')
                    ->schema([
                        Select::make('treatment_id')->label('Tindakan')
                            ->options(fn (): array => Treatment::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required()->distinct()->columnSpan(2),
                        TextInput::make('quota')->label('Kuota')->numeric()->minValue(1)->required()->suffix('kali'),
                    ])
                    ->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif dijual')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items.treatment'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (Package $record): string => $record->items->map(fn ($i): string => $i->treatment->name.' ×'.$i->quota)->implode(', ')),
                TextColumn::make('price')->label('Harga')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false)),
                TextColumn::make('validity_days')->label('Berlaku')->suffix(' hari')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Aktif')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePackages::route('/')];
    }
}
