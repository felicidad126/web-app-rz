<?php

namespace App\Services\Clinic;

use App\Enums\RecallStatus;
use App\Enums\TreatmentPlanItemStatus;
use App\Enums\VisitStatus;
use App\Models\Recall;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tanda tangan dokter: mengunci rekam medis, menutup kunjungan, menandai item rencana
 * yang dikerjakan, dan membuat recall untuk tindakan yang punya interval kontrol.
 */
final class MedicalRecordSigning
{
    /** @return list<string> Hal yang belum lengkap. Kosong = siap ditandatangani. */
    public static function problems(Visit $visit): array
    {
        $record = $visit->medicalRecord;
        $problems = [];

        if ($record === null) {
            return ['Rekam medis belum diisi.'];
        }

        if ($record->isSigned()) {
            return ['Rekam medis sudah ditandatangani.'];
        }

        if (blank($record->subjective)) {
            $problems[] = 'Anamnesis (keluhan) belum diisi.';
        }

        if (! $record->diagnoses()->exists()) {
            $problems[] = 'Belum ada diagnosis ICD-10.';
        }

        if ($visit->status === VisitStatus::Cancelled) {
            $problems[] = 'Kunjungan sudah '.$visit->status->getLabel().'.';
        }

        return $problems;
    }

    public static function sign(Visit $visit, User $user): void
    {
        $visit->loadMissing('medicalRecord');

        if (($problems = self::problems($visit)) !== []) {
            throw new InvalidArgumentException(implode(' ', $problems));
        }

        DB::transaction(function () use ($visit, $user): void {
            $record = $visit->medicalRecord;
            $record->forceFill(['signed_by' => $user->id, 'signed_at' => now()])->save();

            $visit->update([
                // Kunjungan yang sudah ditagih (dokter menandatangani belakangan) tetap "sudah ditagih".
                'status' => $visit->status === VisitStatus::Billed ? VisitStatus::Billed : VisitStatus::Done,
                'started_at' => $visit->started_at ?? now(),
                'finished_at' => now(),
            ]);

            $procedures = $visit->procedures()->with(['planItem.plan', 'treatment'])->get();

            foreach ($procedures as $procedure) {
                if ($item = $procedure->planItem) {
                    $item->update(['status' => TreatmentPlanItemStatus::Done]);
                    $item->plan->syncProgress();
                }

                self::scheduleRecall($visit, $procedure->treatment_id, $procedure->treatment->name, $procedure->treatment->recall_months);
            }

            activity('audit')
                ->performedOn($record)
                ->causedBy($user)
                ->event('signed')
                ->log('Rekam medis ditandatangani dan dikunci');
        });
    }

    private static function scheduleRecall(Visit $visit, int $treatmentId, string $treatmentName, ?int $months): void
    {
        if (! $months) {
            return;
        }

        $open = [RecallStatus::Pending, RecallStatus::Contacted, RecallStatus::Booked];

        // Recall lama untuk tindakan yang sama dianggap selesai: pasien sudah datang.
        Recall::where('patient_id', $visit->patient_id)
            ->where('treatment_id', $treatmentId)
            ->whereIn('status', $open)
            ->update(['status' => RecallStatus::Done]);

        Recall::create([
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'treatment_id' => $treatmentId,
            'visit_id' => $visit->id,
            'reason' => 'Kontrol: '.$treatmentName,
            'due_date' => now()->addMonths($months)->toDateString(),
            'status' => RecallStatus::Pending,
        ]);
    }
}
