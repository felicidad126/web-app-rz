<?php

namespace App\Filament\Resources\Visits\RelationManagers;

use App\Enums\AttachmentType;
use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsRelationManager extends ClinicalRelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran';

    protected static ?string $modelLabel = 'lampiran';

    protected static bool $editableAfterLock = true;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::fields((int) $this->getOwnerRecord()->getAttribute('patient_id')));
    }

    /** @return array<int, mixed> */
    public static function fields(int $patientId): array
    {
        return [
            Select::make('type')->label('Jenis')->options(AttachmentType::class)->required(),
            DatePicker::make('taken_at')->label('Tanggal diambil')->native(false)->displayFormat('d/m/Y')->default(today()),
            FileUpload::make('path')
                ->label('File')
                ->disk('local')
                ->directory('attachments/'.$patientId)
                ->visibility('private')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/dicom'])
                ->maxSize(20 * 1024)
                ->storeFileNamesIn('original_name')
                ->required()
                ->columnSpanFull(),
            TextInput::make('notes')->label('Keterangan')->placeholder('Mis. periapikal 36 sebelum tambal')->maxLength(255)->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('taken_at', 'desc')
            ->columns(self::columns())
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah lampiran')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'patient_id' => $this->getOwnerRecord()->getAttribute('patient_id'),
                        'disk' => 'local',
                    ]),
            ])
            ->recordActions([
                self::openAction(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<int, mixed> */
    public static function columns(): array
    {
        return [
            TextColumn::make('taken_at')->label('Tanggal')->date('d/m/Y')->sortable(),
            TextColumn::make('type')->label('Jenis')->badge(),
            TextColumn::make('original_name')->label('File')->limit(40),
            TextColumn::make('notes')->label('Keterangan')->placeholder('—')->limit(40),
            TextColumn::make('uploader.name')->label('Diunggah oleh')->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function openAction(): Action
    {
        return Action::make('open')
            ->label('Buka')
            ->icon('heroicon-o-eye')
            ->url(fn (Attachment $record): string => route('clinic.attachments.show', $record))
            ->openUrlInNewTab();
    }
}
