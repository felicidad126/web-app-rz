<?php

namespace App\Filament\Resources\Visits\RelationManagers;

use App\Enums\SignerRelation;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Forms\SignaturePad;
use App\Models\ConsentTemplate;
use App\Models\InformedConsent;
use App\Models\Visit;
use App\Services\Clinic\ConsentRecorder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class InformedConsentsRelationManager extends ClinicalRelationManager
{
    protected static string $relationship = 'informedConsents';

    protected static ?string $title = 'Persetujuan tindakan';

    protected static ?string $modelLabel = 'persetujuan';

    protected static bool $editableAfterLock = true;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul'),
                TextColumn::make('signer_name')
                    ->label('Ditandatangani oleh')
                    ->description(fn (InformedConsent $record): string => $record->signer_relation->getLabel()),
                TextColumn::make('signed_at')->label('Waktu')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                $this->signAction(),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (InformedConsent $record): string => route('clinic.consents.pdf', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (InformedConsent $record): bool => filled($record->pdf_path)),
            ]);
    }

    private function signAction(): Action
    {
        /** @var Visit $visit */
        $visit = $this->getOwnerRecord();

        return Action::make('signConsent')
            ->label('Buat & tanda tangani')
            ->icon(Heroicon::OutlinedPencil)
            ->hidden(fn (): bool => $this->isReadOnly())
            ->modalHeading('Persetujuan tindakan')
            ->modalDescription('Serahkan tablet ke pasien atau wali setelah isi diperiksa dokter.')
            ->modalWidth('3xl')
            ->schema([
                Select::make('consent_template_id')
                    ->label('Template')
                    ->options(fn (): array => ConsentTemplate::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->live()
                    ->afterStateUpdated(fn ($state, Get $get, Set $set) => $this->applyTemplate($visit, $state, $get, $set))
                    ->required(),
                TextInput::make('procedure')
                    ->label('Tindakan yang akan dilakukan')
                    ->placeholder('Mis. pencabutan sisa akar gigi 18')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Get $get, Set $set) => $this->applyTemplate($visit, $get('consent_template_id'), $get, $set))
                    ->required(),
                TextInput::make('title')->label('Judul')->required()->maxLength(255),
                Textarea::make('content')->label('Isi persetujuan')->rows(10)->required(),
                Select::make('practitioner_id')->label('Dokter yang menjelaskan')->options(fn (): array => PractitionerOptions::dentists())->default($visit->practitioner_id)->required(),
                Select::make('signer_relation')
                    ->label('Penanda tangan')
                    ->options(SignerRelation::class)
                    ->default(SignerRelation::Self)
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('signer_name', $state === SignerRelation::Self->value || $state === SignerRelation::Self ? $visit->patient->name : null))
                    ->required(),
                TextInput::make('signer_name')->label('Nama penanda tangan')->default($visit->patient->name)->required()->maxLength(255),
                SignaturePad::make('signature')->label('Tanda tangan')->required(),
            ])
            ->action(function (array $data) use ($visit): void {
                try {
                    ConsentRecorder::record($visit, [
                        'consent_template_id' => $data['consent_template_id'] ?? null,
                        'practitioner_id' => (int) $data['practitioner_id'],
                        'title' => $data['title'],
                        'content' => $data['content'],
                        'signer_name' => $data['signer_name'],
                        'signer_relation' => $data['signer_relation'] instanceof SignerRelation ? $data['signer_relation']->value : $data['signer_relation'],
                        'signature' => (string) $data['signature'],
                    ]);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Persetujuan tersimpan beserta PDF-nya')->success()->send();
            });
    }

    private function applyTemplate(Visit $visit, mixed $templateId, Get $get, Set $set): void
    {
        $template = $templateId ? ConsentTemplate::find($templateId) : null;

        if (! $template) {
            return;
        }

        $set('title', $template->name);
        $set('content', ConsentTemplate::render($template->body, ConsentTemplate::valuesFor($visit, $get('procedure'))));
    }
}
