<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Enums\VisitStatus;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Resources\Visits\Schemas\ExamForm;
use App\Filament\Resources\Visits\VisitActions;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\User;
use App\Models\Visit;
use App\Services\Clinic\MedicalRecordSigning;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;

/**
 * Halaman pemeriksaan (rekam medis) satu kunjungan.
 *
 * Dokter & perawat mengisi; hanya dokter yang menandatangani. Setelah ditandatangani semua isian terkunci.
 */
class ExamineVisit extends EditRecord
{
    protected static string $resource = VisitResource::class;

    protected static ?string $title = 'Pemeriksaan';

    protected static ?string $breadcrumb = 'Pemeriksaan';

    public function mount(int|string $record): void
    {
        abort_unless(auth()->user()?->can('View:MedicalRecord'), 403);

        parent::mount($record);

        /** @var Visit $visit */
        $visit = $this->getRecord();

        if (! $visit->isLocked() && $this->canEditRecord() && $visit->status !== VisitStatus::Cancelled) {
            $visit->ensureMedicalRecord();
            $visit->load('medicalRecord');
            $this->fillForm();
        }

        // UU PDP & Permenkes 24/2022: catat setiap akses ke rekam medis.
        activity('access')
            ->performedOn($visit)
            ->causedBy(auth()->user())
            ->event('viewed')
            ->log('Membuka rekam medis kunjungan');
    }

    /** Halaman ini dibaca oleh peran yang boleh melihat rekam medis (termasuk Owner, hanya baca). */
    protected function authorizeAccess(): void
    {
        abort_unless(
            (auth()->user()?->can('View:MedicalRecord') ?? false) && static::getResource()::canView($this->getRecord()),
            403,
        );
    }

    protected function beforeSave(): void
    {
        abort_if($this->isReadOnly(), 403);
    }

    public function form(Schema $schema): Schema
    {
        /** @var Visit $visit */
        $visit = $this->getRecord();
        $visit->loadMissing('patient');

        return ExamForm::configure($schema, $visit)->disabled($this->isReadOnly());
    }

    public function isReadOnly(): bool
    {
        /** @var Visit $visit */
        $visit = $this->getRecord();

        return $visit->isLocked() || ! $this->canEditRecord() || $visit->status === VisitStatus::Cancelled;
    }

    private function canEditRecord(): bool
    {
        return auth()->user()?->can('Update:MedicalRecord') ?? false;
    }

    public function getHeading(): string|Htmlable
    {
        /** @var Visit $visit */
        $visit = $this->getRecord();

        return 'Pemeriksaan · '.$visit->patient->name;
    }

    public function getSubheading(): ?string
    {
        /** @var Visit $visit */
        $visit = $this->getRecord();
        $record = $visit->medicalRecord;
        $signed = $record?->signed_at ? ' · ditandatangani '.$record->signer?->name.' '.$record->signed_at->format('d/m/Y H:i') : '';

        return $visit->visit_number.' · '.$visit->visit_date->format('d/m/Y').' · '.$visit->practitioner->user->name
            .' · '.$visit->status->getLabel().$signed;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Pemeriksaan & tindakan';
    }

    protected function getFormActions(): array
    {
        return $this->isReadOnly() ? [] : [$this->getSaveFormAction()->label('Simpan draf')];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draf rekam medis disimpan';
    }

    protected function getHeaderActions(): array
    {
        return [
            VisitActions::start(),
            $this->signAction(),
            ActionGroup::make([
                Action::make('patient')
                    ->label('Data pasien')
                    ->icon(Heroicon::OutlinedUser)
                    ->url(fn (Visit $record): string => PatientResource::getUrl('view', ['record' => $record->patient_id])),
                Action::make('odontogram')
                    ->label('Odontogram pasien')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (Visit $record): string => PatientResource::getUrl('odontogram', ['record' => $record->patient_id])),
                Action::make('plan')
                    ->label('Buat rencana perawatan')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->visible(fn (): bool => auth()->user()?->can('Create:TreatmentPlan') ?? false)
                    ->url(fn (Visit $record): string => TreatmentPlanResource::getUrl('create', ['patient' => $record->patient_id, 'visit' => $record->id])),
                VisitActions::cancel(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function signAction(): Action
    {
        return Action::make('sign')
            ->label('Tanda tangani & kunci')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('primary')
            ->visible(fn (Visit $record): bool => ! $record->isLocked() && $record->status !== VisitStatus::Cancelled)
            ->authorize(fn (): bool => auth()->user()?->can('Sign:MedicalRecord') ?? false)
            ->modalHeading(fn (): string => $this->readyToSign() ? 'Tanda tangani rekam medis' : 'Belum bisa ditandatangani')
            ->modalDescription(fn (): string => $this->readyToSign()
                ? 'Setelah ditandatangani, rekam medis kunjungan ini terkunci. Koreksi berikutnya hanya lewat addendum.'
                : 'Lengkapi isian bertanda merah di form pemeriksaan, lalu klik "Tanda tangani & kunci" lagi.')
            ->modalContent(fn (): View => view('filament.clinic.sign-checklist', ['items' => $this->signChecklist()]))
            ->modalSubmitActionLabel('Tanda tangani')
            ->modalSubmitAction(fn (Action $action): Action|false => $this->readyToSign() ? $action : false)
            ->modalCancelActionLabel(fn (): string => $this->readyToSign() ? 'Batal' : 'Kembali mengisi')
            ->schema([
                TextInput::make('password')
                    ->label('Password Anda')
                    ->password()
                    ->revealable()
                    ->currentPassword()
                    ->required()
                    ->visible(fn (): bool => $this->readyToSign()),
            ])
            ->action(function (Visit $record): void {
                // Simpan isian terakhir dulu agar yang ditandatangani sama dengan yang terlihat.
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $record->refresh();

                try {
                    /** @var User $user */
                    $user = auth()->user();
                    MedicalRecordSigning::sign($record, $user);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Belum bisa ditandatangani')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Rekam medis ditandatangani dan dikunci')->success()->send();
                $this->redirect(VisitResource::getUrl('examine', ['record' => $record]));
            });
    }

    /**
     * Daftar periksa dari isian form saat ini (termasuk yang belum disimpan).
     * Aturan finalnya tetap di {@see MedicalRecordSigning::problems()} saat menandatangani.
     *
     * @return list<array{label: string, done: bool, required: bool, note?: string}>
     */
    public function signChecklist(): array
    {
        $count = function (string $path, string $key): int {
            $rows = data_get($this->data, $path);

            return collect(is_array($rows) ? $rows : [])->filter(fn (mixed $row): bool => is_array($row) && filled($row[$key] ?? null))->count();
        };

        $diagnoses = $count('medicalRecord.diagnoses', 'icd10_code');
        $procedures = $count('procedures', 'treatment_id');

        return [
            ['label' => 'Keluhan & anamnesis (S)', 'done' => filled(data_get($this->data, 'medicalRecord.subjective')), 'required' => true],
            ['label' => 'Diagnosis ICD-10', 'done' => $diagnoses > 0, 'required' => true, 'note' => $diagnoses.' tercatat'],
            ['label' => 'Tindakan', 'done' => $procedures > 0, 'required' => false, 'note' => $procedures.' tercatat'],
        ];
    }

    private function readyToSign(): bool
    {
        return collect($this->signChecklist())->every(fn (array $item): bool => $item['done'] || ! $item['required']);
    }
}
