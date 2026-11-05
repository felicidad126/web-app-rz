<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\Visits\RelationManagers\AttachmentsRelationManager as VisitAttachments;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran';

    protected static ?string $modelLabel = 'lampiran';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('View:MedicalRecord') ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('Update:MedicalRecord') ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(VisitAttachments::fields((int) $this->getOwnerRecord()->getKey()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('taken_at', 'desc')
            ->columns([
                ...VisitAttachments::columns(),
                TextColumn::make('visit.visit_number')->label('Kunjungan')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Unggah lampiran')->mutateDataUsing(fn (array $data): array => [...$data, 'disk' => 'local']),
            ])
            ->recordActions([VisitAttachments::openAction()]);
    }
}
