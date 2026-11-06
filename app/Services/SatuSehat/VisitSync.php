<?php

namespace App\Services\SatuSehat;

use App\Enums\SatuSehatIssue;
use App\Enums\SatuSehatSyncStatus;
use App\Jobs\SendVisitToSatuSehat;
use App\Models\SatuSehatSync;
use App\Models\Visit;

/**
 * Mengirim satu kunjungan ke SATUSEHAT sebagai Bundle transaksi dan mencatat hasilnya di `satusehat_syncs`.
 *
 * Kirim ulang aman: kunjungan yang sudah diterima tidak dikirim lagi, dan Encounter memakai identifier
 * nomor kunjungan sehingga SATUSEHAT bisa mengenali data yang sama.
 */
final class VisitSync
{
    public const RESOURCE = 'Encounter';

    public function __construct(
        private readonly SatuSehatClient $client,
        private readonly IhsLookup $ihs,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('satusehat.enabled');
    }

    /** Masukkan kunjungan ke antrean kirim (dipanggil setelah tanda tangan, atau "Kirim ulang"). */
    public static function queue(Visit $visit, bool $force = false): ?SatuSehatSync
    {
        if (! self::enabled()) {
            return null;
        }

        $sync = self::record($visit);

        if ($sync->status === SatuSehatSyncStatus::Sent && ! $force) {
            return $sync;
        }

        if ($sync->status !== SatuSehatSyncStatus::Sent) {
            $sync->forceFill(['status' => SatuSehatSyncStatus::Pending])->save();
        }

        SendVisitToSatuSehat::dispatch($visit->id)->afterCommit();

        return $sync;
    }

    public static function record(Visit $visit): SatuSehatSync
    {
        return SatuSehatSync::firstOrCreate(
            ['syncable_type' => $visit->getMorphClass(), 'syncable_id' => $visit->id, 'resource_type' => self::RESOURCE],
            ['branch_id' => $visit->branch_id, 'status' => SatuSehatSyncStatus::Pending],
        );
    }

    public function send(Visit $visit): SatuSehatSync
    {
        $sync = self::record($visit);

        if ($sync->status === SatuSehatSyncStatus::Sent) {
            return $sync;
        }

        $sync->forceFill(['attempts' => $sync->attempts + 1, 'last_attempt_at' => now()])->save();

        try {
            $bundle = $this->build($visit);
            $sync->forceFill(['resource_counts' => $bundle->counts(), 'warnings' => $bundle->warnings() ?: null])->save();

            $response = $this->client->post('', $bundle->toArray());
            $ids = $bundle->idsFromResponse($response);

            $sync->forceFill([
                'status' => SatuSehatSyncStatus::Sent,
                'satusehat_id' => $ids[self::RESOURCE][array_key_first($ids[self::RESOURCE] ?? []) ?? ''] ?? null,
                'resource_ids' => $ids,
                'issue_code' => null,
                'last_error' => null,
                'sent_at' => now(),
            ])->save();
        } catch (SatuSehatException $e) {
            $sync->forceFill([
                'status' => $e->isTransient() ? SatuSehatSyncStatus::Failed : SatuSehatSyncStatus::Blocked,
                'issue_code' => $e->issue,
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $sync;
    }

    /** Periksa kelengkapan, cari IHS bila belum ada, lalu susun Bundle. */
    public function build(Visit $visit): EncounterBundle
    {
        if (! $this->client->isConfigured()) {
            throw new SatuSehatException('Kredensial atau Organization ID SATUSEHAT belum diisi.', SatuSehatIssue::NotConfigured);
        }

        $visit->load([
            'branch', 'patient', 'practitioner.user', 'medicalRecord.diagnoses.icd10',
            'procedures.treatment', 'procedures.operator.user', 'prescription.items.item', 'prescription.items.unit',
        ]);

        if ($visit->medicalRecord?->signed_at === null) {
            throw new SatuSehatException("Rekam medis {$visit->visit_number} belum ditandatangani.", SatuSehatIssue::NotSigned);
        }

        if (blank($visit->branch->satusehat_location_id)) {
            throw new SatuSehatException("Cabang {$visit->branch->name} belum punya Location ID SATUSEHAT.", SatuSehatIssue::LocationMissing);
        }

        foreach ($visit->procedures as $procedure) {
            if (blank($procedure->icd9cm_code ?? $procedure->treatment->icd9cm_code)) {
                throw new SatuSehatException("Tindakan \"{$procedure->treatment->name}\" belum punya kode ICD-9-CM.", SatuSehatIssue::ProcedureCodeMissing);
            }
        }

        // IHS dokter penanggung jawab + operator tiap tindakan (bisa berbeda, mis. dokter spesialis).
        $practitioners = [$visit->practitioner_id => $this->ihs->practitioner($visit->practitioner)];

        foreach ($visit->procedures as $procedure) {
            $practitioners[$procedure->operator_id] ??= $this->ihs->practitioner($procedure->operator);
        }

        return new EncounterBundle(
            visit: $visit,
            organizationId: $this->client->organizationId(),
            patientId: $this->ihs->patient($visit->patient),
            practitionerIds: $practitioners,
        );
    }
}
