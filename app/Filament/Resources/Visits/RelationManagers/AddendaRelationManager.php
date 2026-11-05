<?php

namespace App\Filament\Resources\Visits\RelationManagers;

use App\Models\MedicalRecordAddendum;
use App\Models\Visit;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AddendaRelationManager extends ClinicalRelationManager
{
    protected static string $relationship = 'addenda';

    protected static ?string $title = 'Addendum';

    protected static ?string $modelLabel = 'addendum';

    protected static bool $editableAfterLock = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Visit $ownerRecord */
        return parent::canViewForRecord($ownerRecord, $pageClass) && $ownerRecord->isLocked();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('content')
                ->label('Isi addendum')
                ->helperText('Addendum tidak bisa diubah atau dihapus setelah disimpan.')
                ->rows(4)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Koreksi atau tambahan setelah rekam medis dikunci.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('content')->label('Isi')->wrap(),
                TextColumn::make('author.name')->label('Oleh'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah addendum')
                    ->authorize(fn (): bool => auth()->user()?->can('Sign:MedicalRecord') ?? false)
                    ->using(function (array $data): MedicalRecordAddendum {
                        /** @var Visit $visit */
                        $visit = $this->getOwnerRecord();

                        return $visit->medicalRecord->addenda()->create($data);
                    }),
            ]);
    }
}
