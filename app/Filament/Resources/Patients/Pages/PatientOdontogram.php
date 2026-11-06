<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Enums\OdontogramScope;
use App\Filament\Clinic\Concerns\EditsOdontogramChart;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Visits\RelationManagers\OdontogramEntriesRelationManager;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Clinic\OdontogramChart;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Odontogram pasien: status terkini per gigi, indeks DMF-T, dan riwayat catatan.
 * Catatan tanpa kunjungan dipakai untuk mencatat kondisi awal (mis. pasien pindahan).
 */
class PatientOdontogram extends Page
{
    use EditsOdontogramChart;
    use InteractsWithRecord;

    protected static string $resource = PatientResource::class;

    protected string $view = 'filament.clinic.patient-odontogram';

    protected static ?string $title = 'Odontogram';

    public function mount(int|string $record): void
    {
        abort_unless(auth()->user()?->can('View:MedicalRecord'), 403);

        $this->record = $this->resolveRecord($record);

        activity('access')->performedOn($this->record)->causedBy(auth()->user())->event('viewed')->log('Membuka odontogram pasien');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var Patient $patient */
        $patient = $this->getRecord();

        return 'Odontogram · '.$patient->name;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Patient $patient */
        $patient = $this->getRecord();
        $chart = new OdontogramChart($patient);

        return [
            'patient' => $patient,
            'chart' => $this->odontogramChartData(),
            'editable' => $this->canEditOdontogramChart(),
            'indices' => $chart->indices(),
            'exam' => $patient->oralExaminations()->latest('id')->first(),
            'entries' => $patient->odontogramEntries()->with(['condition', 'visit', 'creator'])->latest('id')->limit(100)->get(),
        ];
    }

    protected function odontogramPatient(): Patient
    {
        /** @var Patient $patient */
        $patient = $this->getRecord();

        return $patient;
    }

    /** Catatan dari halaman pasien adalah kondisi awal (tanpa kunjungan). */
    protected function odontogramVisit(): ?Visit
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addBaseline')
                ->label('Catat kondisi awal')
                ->icon(Heroicon::OutlinedPlus)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('Update:MedicalRecord') ?? false)
                ->modalHeading('Catat kondisi awal (tanpa kunjungan)')
                ->modalDescription('Untuk kondisi yang sudah ada sebelum pasien berobat di sini. Pencatatan saat pemeriksaan dilakukan di halaman kunjungan.')
                ->schema(OdontogramEntriesRelationManager::fields())
                ->action(function (array $data): void {
                    $scope = OdontogramCondition::where('code', $data['condition_code'])->value('applies_to');
                    OdontogramEntry::create([
                        ...$data,
                        'patient_id' => $this->getRecord()->getKey(),
                        'surface' => $scope === OdontogramScope::Tooth ? null : ($data['surface'] ?? null),
                    ]);
                    Notification::make()->title('Catatan odontogram ditambahkan')->success()->send();
                }),
            Action::make('print')
                ->label('Cetak PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => route('clinic.odontogram.pdf', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
