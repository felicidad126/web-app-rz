<?php

namespace App\Filament\Resources\ConsentTemplates;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\ConsentTemplates\Pages\ManageConsentTemplates;
use App\Filament\Support\NavGroup;
use App\Models\ConsentTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ConsentTemplateResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = ConsentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CLINICAL_MASTER;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'template persetujuan';

    protected static ?string $pluralModelLabel = 'template persetujuan';

    protected static ?string $navigationLabel = 'Template persetujuan';

    protected static ?string $slug = 'template-persetujuan';

    public static function form(Schema $schema): Schema
    {
        $placeholders = collect(ConsentTemplate::PLACEHOLDERS)
            ->map(fn (string $label, string $key): string => '{{'.$key.'}} '.$label)
            ->implode(' · ');

        return $schema->columns(1)->components([
            TextInput::make('name')->label('Nama template')->required()->maxLength(255),
            Textarea::make('body')
                ->label('Isi')
                ->rows(16)
                ->helperText('Teks biasa. Placeholder yang tersedia: '.$placeholders)
                ->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Diubah')->dateTime('d/m/Y'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageConsentTemplates::route('/')];
    }
}
