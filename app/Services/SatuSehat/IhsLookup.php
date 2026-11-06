<?php

namespace App\Services\SatuSehat;

use App\Enums\SatuSehatIssue;
use App\Models\Patient;
use App\Models\Practitioner;

/**
 * Mencari IHS Number pasien & nakes dari NIK, lalu menyimpannya di master (sekali cari, dipakai seterusnya).
 */
final class IhsLookup
{
    public const NIK_SYSTEM = 'https://fhir.kemkes.go.id/id/nik';

    public function __construct(private readonly SatuSehatClient $client) {}

    public function patient(Patient $patient, bool $refresh = false): string
    {
        if (filled($patient->satusehat_ihs_id) && ! $refresh) {
            return (string) $patient->satusehat_ihs_id;
        }

        if (blank($patient->nik)) {
            throw new SatuSehatException("Pasien {$patient->rm_number} belum punya NIK.", SatuSehatIssue::PatientNikMissing);
        }

        $id = $this->searchByNik('Patient', (string) $patient->nik)
            ?? throw new SatuSehatException("NIK pasien {$patient->rm_number} tidak ditemukan di SATUSEHAT.", SatuSehatIssue::PatientNotFound);

        $patient->forceFill(['satusehat_ihs_id' => $id])->save();

        return $id;
    }

    public function practitioner(Practitioner $practitioner, bool $refresh = false): string
    {
        $practitioner->loadMissing('user');

        if (filled($practitioner->satusehat_ihs_id) && ! $refresh) {
            return (string) $practitioner->satusehat_ihs_id;
        }

        if (blank($practitioner->nik)) {
            throw new SatuSehatException("{$practitioner->user->name} belum punya NIK.", SatuSehatIssue::PractitionerNikMissing);
        }

        $id = $this->searchByNik('Practitioner', (string) $practitioner->nik)
            ?? throw new SatuSehatException("NIK {$practitioner->user->name} tidak ditemukan sebagai nakes di SATUSEHAT.", SatuSehatIssue::PractitionerNotFound);

        $practitioner->forceFill(['satusehat_ihs_id' => $id])->save();

        return $id;
    }

    private function searchByNik(string $resource, string $nik): ?string
    {
        $bundle = $this->client->get($resource, ['identifier' => self::NIK_SYSTEM.'|'.$nik]);
        $id = data_get($bundle, 'entry.0.resource.id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
