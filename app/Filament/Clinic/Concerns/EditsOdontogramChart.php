<?php

namespace App\Filament\Clinic\Concerns;

use App\Enums\OdontogramEntryType;
use App\Enums\OdontogramScope;
use App\Enums\ToothSurface;
use App\Exceptions\MedicalRecordLockedException;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Clinic\OdontogramChart;
use App\Services\Clinic\OdontogramVisual;
use App\Support\Teeth;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Simpan catatan dari odontogram visual (klik gigi/permukaan). Dipakai di tab Odontogram pemeriksaan dan halaman
 * Odontogram pasien. Aturannya sama dengan input tabel: riwayat tidak ditimpa, satu baris per permukaan,
 * kode "seluruh gigi" tanpa permukaan, dan terkunci bila rekam medis kunjungan sudah ditandatangani.
 */
trait EditsOdontogramChart
{
    abstract protected function odontogramPatient(): Patient;

    abstract protected function odontogramVisit(): ?Visit;

    public function canEditOdontogramChart(): bool
    {
        $visit = $this->odontogramVisit();

        return (auth()->user()?->can('Update:MedicalRecord') ?? false) && ($visit === null || ! $visit->isLocked());
    }

    /** @return array<string, mixed> */
    protected function odontogramChartData(): array
    {
        return (new OdontogramVisual(new OdontogramChart($this->odontogramPatient()), $this->odontogramVisit()))->data();
    }

    /**
     * @param  list<string>  $surfaces
     * @return array{ok: bool, message: string, chart?: array<string, mixed>}
     */
    public function saveOdontogramFromChart(string $tooth, array $surfaces, string $code, string $type, ?string $notes = null): array
    {
        $fail = function (string $message): array {
            Notification::make()->title('Belum tersimpan')->body($message)->danger()->send();

            return ['ok' => false, 'message' => $message];
        };

        if (! $this->canEditOdontogramChart()) {
            return $fail('Anda tidak bisa mengubah odontogram ini (izin atau rekam medis sudah ditandatangani).');
        }

        $visit = $this->odontogramVisit();
        $entryType = OdontogramEntryType::tryFrom($type);
        $condition = OdontogramCondition::query()->where('code', $code)->where('is_active', true)->first();
        $surfaces = array_values(array_unique(array_filter($surfaces, fn ($s): bool => ToothSurface::tryFrom((string) $s) !== null)));

        if (! Teeth::isValid($tooth)) {
            return $fail('Nomor gigi FDI tidak valid.');
        }

        if ($condition === null) {
            return $fail('Pilih kode kondisi.');
        }

        if ($entryType === null || ($entryType === OdontogramEntryType::Done && $visit === null)) {
            return $fail('Jenis catatan tidak valid. "Dikerjakan" hanya dari halaman kunjungan.');
        }

        $wholeTooth = $condition->applies_to === OdontogramScope::Tooth;

        if (! $wholeTooth && $surfaces === []) {
            return $fail('Pilih minimal satu permukaan untuk kode '.$condition->code.'.');
        }

        try {
            DB::transaction(function () use ($tooth, $surfaces, $condition, $entryType, $notes, $visit, $wholeTooth): void {
                foreach ($wholeTooth ? [null] : $surfaces as $surface) {
                    OdontogramEntry::create([
                        'patient_id' => $this->odontogramPatient()->id,
                        'visit_id' => $visit?->id,
                        'tooth_number' => $tooth,
                        'surface' => $surface,
                        'condition_code' => $condition->code,
                        'entry_type' => $entryType,
                        'notes' => filled($notes) ? mb_substr(trim((string) $notes), 0, 255) : null,
                    ]);
                }
            });
        } catch (MedicalRecordLockedException) {
            return $fail('Rekam medis kunjungan ini sudah ditandatangani. Koreksi lewat addendum.');
        }

        $message = 'Gigi '.$tooth.': '.$condition->name.($wholeTooth ? '' : ' '.implode('', $surfaces)).' · '.$entryType->getLabel();
        Notification::make()->title('Odontogram dicatat')->body($message)->success()->send();

        return ['ok' => true, 'message' => $message, 'chart' => $this->odontogramChartData()];
    }
}
