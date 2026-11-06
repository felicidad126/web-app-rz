<?php

namespace App\Services\SatuSehat;

use App\Enums\SatuSehatIssue;
use App\Models\Branch;

/**
 * Mendaftarkan ruang poli gigi tiap cabang sebagai Location di SATUSEHAT (dipakai di Encounter).
 */
final class LocationRegistrar
{
    public function __construct(private readonly SatuSehatClient $client) {}

    public function register(Branch $branch): string
    {
        if (filled($branch->satusehat_location_id)) {
            return (string) $branch->satusehat_location_id;
        }

        $organizationId = $this->client->organizationId();
        $response = $this->client->post('Location', [
            'resourceType' => 'Location',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/location/'.$organizationId,
                'value' => 'POLI-GIGI-'.$branch->code,
            ]],
            'status' => 'active',
            'name' => 'Poli Gigi '.$branch->name,
            'description' => 'Ruang pelayanan gigi rawat jalan, '.$branch->name,
            'mode' => 'instance',
            'address' => array_filter(['use' => 'work', 'line' => $branch->address ? [$branch->address] : null, 'country' => 'ID']),
            'physicalType' => ['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/location-physical-type',
                'code' => 'ro',
                'display' => 'Room',
            ]]],
            'managingOrganization' => ['reference' => 'Organization/'.$organizationId],
            // FHIR JSON tidak boleh berisi array kosong; telecom hanya bila ada nomor telepon.
        ] + ($branch->phone ? ['telecom' => [['system' => 'phone', 'value' => $branch->phone, 'use' => 'work']]] : []));

        $id = $response['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new SatuSehatException('SATUSEHAT tidak mengembalikan ID Location.', SatuSehatIssue::Rejected);
        }

        $branch->forceFill(['satusehat_location_id' => $id])->save();

        return $id;
    }
}
