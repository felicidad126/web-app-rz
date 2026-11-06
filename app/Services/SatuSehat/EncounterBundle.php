<?php

namespace App\Services\SatuSehat;

use App\Enums\DiagnosisType;
use App\Models\Diagnosis;
use App\Models\Icd9cmCode;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Models\VisitProcedure;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Satu kunjungan rawat jalan sebagai FHIR Bundle "transaction" (playbook SATUSEHAT Rawat Jalan):
 * Encounter (finished + statusHistory) · Condition (ICD-10) · Observation (tanda vital, LOINC)
 * · Procedure (ICD-9-CM) · Medication + MedicationRequest (KFA).
 *
 * Referensi antar-resource memakai `urn:uuid:` sehingga semuanya terkirim dalam satu permintaan
 * yang atomik: diterima semua atau ditolak semua.
 */
final class EncounterBundle
{
    public const ICD10 = 'http://hl7.org/fhir/sid/icd-10';

    public const ICD9CM = 'http://hl7.org/fhir/sid/icd-9-cm';

    public const LOINC = 'http://loinc.org';

    public const UCUM = 'http://unitsofmeasure.org';

    public const KFA = 'http://sys-ids.kemkes.go.id/kfa';

    /** Tanda vital di rekam medis → LOINC & UCUM. */
    private const VITALS = [
        'pulse' => ['8867-4', 'Heart rate', 'beats/minute', '/min'],
        'respiratory_rate' => ['9279-1', 'Respiratory rate', 'breaths/minute', '/min'],
        'temperature' => ['8310-5', 'Body temperature', 'C', 'Cel'],
    ];

    /** @var list<array{fullUrl: string, resource: array<string, mixed>, request: array{method: string, url: string}}> */
    private array $entries = [];

    /** @var list<array{type: string, key: string}> urutan entri → kunci lokal, untuk memetakan ID balasan */
    private array $keys = [];

    /** @var list<string> */
    private array $warnings = [];

    private string $encounterUrl;

    /**
     * @param  array<int, string>  $practitionerIds  practitioner_id lokal → IHS Practitioner
     */
    public function __construct(
        private readonly Visit $visit,
        private readonly string $organizationId,
        private readonly string $patientId,
        private readonly array $practitionerIds,
    ) {
        $this->encounterUrl = self::uuid();
        $this->build();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => $this->entries,
        ];
    }

    /** @return array<string, int> jumlah resource per jenis (untuk rekap monitoring) */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->keys as $key) {
            $counts[$key['type']] = ($counts[$key['type']] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<string> catatan data yang tidak ikut terkirim (mis. obat tanpa kode KFA) */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Ambil ID SATUSEHAT dari balasan Bundle "transaction-response" (urutan entri sama dengan permintaan).
     *
     * @param  array<string, mixed>  $response
     * @return array<string, array<string, string>> jenis → [kunci lokal → ID]
     */
    public function idsFromResponse(array $response): array
    {
        $ids = [];

        foreach ((array) ($response['entry'] ?? []) as $index => $entry) {
            $key = $this->keys[$index] ?? null;
            $id = self::idFromEntry(is_array($entry) ? $entry : []);

            if ($key !== null && $id !== null) {
                $ids[$key['type']][$key['key']] = $id;
            }
        }

        return $ids;
    }

    /** @param array<string, mixed> $entry */
    private static function idFromEntry(array $entry): ?string
    {
        $id = data_get($entry, 'response.resourceID') ?? data_get($entry, 'resource.id');

        if (! is_string($id) || $id === '') {
            // Bentuk standar FHIR: "Encounter/{id}/_history/{versi}".
            $location = (string) data_get($entry, 'response.location', '');
            $id = explode('/', $location)[1] ?? null;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function build(): void
    {
        $visit = $this->visit;
        $diagnoses = $visit->medicalRecord->diagnoses
            ->sortBy(fn (Diagnosis $d): int => $d->type === DiagnosisType::Primary ? 0 : 1)
            ->values();

        $conditionUrls = $diagnoses->mapWithKeys(fn (Diagnosis $d): array => [$d->id => self::uuid()])->all();

        $this->add('Encounter', (string) $visit->id, $this->encounterUrl, $this->encounter($diagnoses->all(), $conditionUrls));

        foreach ($diagnoses as $diagnosis) {
            $this->add('Condition', (string) $diagnosis->id, $conditionUrls[$diagnosis->id], $this->condition($diagnosis));
        }

        foreach ($this->observations() as $key => $observation) {
            $this->add('Observation', $key, self::uuid(), $observation);
        }

        $codes = Icd9cmCode::query()->whereIn('code', $visit->procedures->map(fn (VisitProcedure $p) => $p->icd9cm_code ?? $p->treatment->icd9cm_code))->pluck('name', 'code');

        foreach ($visit->procedures as $procedure) {
            $this->add('Procedure', (string) $procedure->id, self::uuid(), $this->procedure($procedure, $codes->all()));
        }

        foreach ($visit->prescription->items ?? [] as $index => $item) {
            if (blank($item->kfa_code ?? $item->item->kfa_code)) {
                $this->warnings[] = "Obat \"{$item->item->name}\" tidak dikirim: belum punya kode KFA.";

                continue;
            }

            $medicationUrl = self::uuid();
            $this->add('Medication', (string) $item->id, $medicationUrl, $this->medication($item));
            $this->add('MedicationRequest', (string) $item->id, self::uuid(), $this->medicationRequest($item, $index + 1, $medicationUrl));
        }
    }

    /** @param array<string, mixed> $resource */
    private function add(string $type, string $key, string $fullUrl, array $resource): void
    {
        $this->entries[] = [
            'fullUrl' => $fullUrl,
            'resource' => $resource,
            'request' => ['method' => 'POST', 'url' => $type],
        ];
        $this->keys[] = ['type' => $type, 'key' => $key];
    }

    /**
     * @param  list<Diagnosis>  $diagnoses
     * @param  array<int, string>  $conditionUrls
     * @return array<string, mixed>
     */
    private function encounter(array $diagnoses, array $conditionUrls): array
    {
        $visit = $this->visit;
        [$arrived, $started, $finished] = $this->timeline();

        return [
            'resourceType' => 'Encounter',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/encounter/'.$this->organizationId,
                'value' => $visit->visit_number,
            ]],
            'status' => 'finished',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory',
            ],
            'subject' => $this->patientRef(),
            'participant' => [[
                'type' => [['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                    'code' => 'ATND',
                    'display' => 'attender',
                ]]]],
                'individual' => $this->practitionerRef($visit->practitioner_id, $visit->practitioner->user->name),
            ]],
            'period' => ['start' => self::time($arrived), 'end' => self::time($finished)],
            'location' => [[
                'location' => ['reference' => 'Location/'.$visit->branch->satusehat_location_id, 'display' => $visit->branch->name],
            ]],
            'diagnosis' => array_map(fn (Diagnosis $d, int $i): array => [
                'condition' => ['reference' => $conditionUrls[$d->id], 'display' => $d->icd10->name ?? $d->icd10_code],
                'use' => ['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/diagnosis-role',
                    'code' => 'DD',
                    'display' => 'Discharge diagnosis',
                ]]],
                'rank' => $i + 1,
            ], $diagnoses, array_keys($diagnoses)),
            'statusHistory' => [
                ['status' => 'arrived', 'period' => ['start' => self::time($arrived), 'end' => self::time($started)]],
                ['status' => 'in-progress', 'period' => ['start' => self::time($started), 'end' => self::time($finished)]],
                ['status' => 'finished', 'period' => ['start' => self::time($finished), 'end' => self::time($finished)]],
            ],
            'serviceProvider' => ['reference' => 'Organization/'.$this->organizationId],
        ];
    }

    /**
     * Datang → diperiksa → selesai, dijamin berurutan (validasi SATUSEHAT: akhir harus setelah awal).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: CarbonInterface}
     */
    private function timeline(): array
    {
        $visit = $this->visit;
        $signed = $visit->medicalRecord->signed_at ?? now();
        $arrived = $visit->arrived_at ?? $visit->started_at ?? $signed;
        $started = $visit->started_at ?? $arrived;
        $finished = $visit->finished_at ?? $signed;

        if ($started->lte($arrived)) {
            $started = $arrived->copy()->addMinute();
        }

        if ($finished->lte($started)) {
            $finished = $started->copy()->addMinute();
        }

        return [$arrived, $started, $finished];
    }

    /** @return array<string, mixed> */
    private function condition(Diagnosis $diagnosis): array
    {
        return [
            'resourceType' => 'Condition',
            'clinicalStatus' => ['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                'code' => 'active',
                'display' => 'Active',
            ]]],
            'category' => [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                'code' => 'encounter-diagnosis',
                'display' => 'Encounter Diagnosis',
            ]]]],
            'code' => ['coding' => [[
                'system' => self::ICD10,
                'code' => $diagnosis->icd10_code,
                'display' => $diagnosis->icd10->name ?? $diagnosis->icd10_code,
            ]]],
            'subject' => $this->patientRef(),
            'encounter' => $this->encounterRef(),
            'recordedDate' => self::time($this->visit->medicalRecord->signed_at),
        ] + ($diagnosis->tooth_number ? ['note' => [['text' => 'Gigi '.$diagnosis->tooth_number.($diagnosis->notes ? ' · '.$diagnosis->notes : '')]]] : []);
    }

    /** @return array<string, array<string, mixed>> kunci tanda vital → Observation */
    private function observations(): array
    {
        $vitals = (array) ($this->visit->medicalRecord->vitals ?? []);
        $observations = [];

        if (preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', (string) ($vitals['blood_pressure'] ?? ''), $bp)) {
            $observations['systolic'] = $this->observation('8480-6', 'Systolic blood pressure', (float) $bp[1], 'mm[Hg]', 'mm[Hg]');
            $observations['diastolic'] = $this->observation('8462-4', 'Diastolic blood pressure', (float) $bp[2], 'mm[Hg]', 'mm[Hg]');
        }

        foreach (self::VITALS as $key => [$code, $display, $unit, $ucum]) {
            if (is_numeric($vitals[$key] ?? null)) {
                $observations[$key] = $this->observation($code, $display, (float) $vitals[$key], $unit, $ucum);
            }
        }

        return $observations;
    }

    /** @return array<string, mixed> */
    private function observation(string $code, string $display, float $value, string $unit, string $ucum): array
    {
        $at = self::time($this->timeline()[1]);

        return [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code' => 'vital-signs',
                'display' => 'Vital Signs',
            ]]]],
            'code' => ['coding' => [['system' => self::LOINC, 'code' => $code, 'display' => $display]]],
            'subject' => $this->patientRef(),
            'performer' => [$this->practitionerRef($this->visit->practitioner_id, $this->visit->practitioner->user->name)],
            'encounter' => $this->encounterRef(),
            'effectiveDateTime' => $at,
            'issued' => $at,
            'valueQuantity' => ['value' => $value, 'unit' => $unit, 'system' => self::UCUM, 'code' => $ucum],
        ];
    }

    /**
     * @param  array<string, string>  $codeNames
     * @return array<string, mixed>
     */
    private function procedure(VisitProcedure $procedure, array $codeNames): array
    {
        $code = (string) ($procedure->icd9cm_code ?? $procedure->treatment->icd9cm_code);
        [, $started, $finished] = $this->timeline();
        $note = trim(($procedure->tooth_number ? 'Gigi '.$procedure->tooth_number : '').($procedure->surfaces ? ' permukaan '.$procedure->surfaces : ''));

        return [
            'resourceType' => 'Procedure',
            'status' => 'completed',
            'code' => ['coding' => [['system' => self::ICD9CM, 'code' => $code, 'display' => $codeNames[$code] ?? $procedure->treatment->name]]],
            'subject' => $this->patientRef(),
            'encounter' => $this->encounterRef(),
            'performedPeriod' => ['start' => self::time($started), 'end' => self::time($finished)],
            'performer' => [['actor' => $this->practitionerRef($procedure->operator_id, $procedure->operator->user->name)]],
        ] + ($note !== '' ? ['note' => [['text' => $note]]] : []);
    }

    /** @return array<string, mixed> */
    private function medication(PrescriptionItem $item): array
    {
        return [
            'resourceType' => 'Medication',
            'meta' => ['profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication']],
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/medication/'.$this->organizationId,
                'use' => 'official',
                'value' => $this->visit->visit_number.'-'.$item->id,
            ]],
            'code' => ['coding' => [['system' => self::KFA, 'code' => (string) ($item->kfa_code ?? $item->item->kfa_code), 'display' => $item->item->name]]],
            'status' => 'active',
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                'valueCodeableConcept' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                    'code' => 'NC',
                    'display' => 'Non-compound',
                ]]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function medicationRequest(PrescriptionItem $item, int $sequence, string $medicationUrl): array
    {
        $prescriptionNo = $this->visit->visit_number;

        return [
            'resourceType' => 'MedicationRequest',
            'identifier' => [
                ['system' => 'http://sys-ids.kemkes.go.id/prescription/'.$this->organizationId, 'use' => 'official', 'value' => $prescriptionNo],
                ['system' => 'http://sys-ids.kemkes.go.id/prescription-item/'.$this->organizationId, 'use' => 'official', 'value' => $prescriptionNo.'-'.$sequence],
            ],
            'status' => 'completed',
            'intent' => 'order',
            'category' => [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
                'code' => 'outpatient',
                'display' => 'Outpatient',
            ]]]],
            'medicationReference' => ['reference' => $medicationUrl, 'display' => $item->item->name],
            'subject' => $this->patientRef(),
            'encounter' => $this->encounterRef(),
            'authoredOn' => self::time($this->visit->medicalRecord->signed_at),
            'requester' => $this->practitionerRef($this->visit->practitioner_id, $this->visit->practitioner->user->name),
            'dosageInstruction' => [['sequence' => 1, 'text' => $item->signa.($item->notes ? ' · '.$item->notes : '')]],
            'dispenseRequest' => [
                'quantity' => ['value' => (float) $item->qty, 'unit' => $item->unit->name ?? ''],
                'performer' => ['reference' => 'Organization/'.$this->organizationId],
            ],
        ];
    }

    /** @return array{reference: string, display: string} */
    private function patientRef(): array
    {
        return ['reference' => 'Patient/'.$this->patientId, 'display' => $this->visit->patient->name];
    }

    /** @return array{reference: string, display: string} */
    private function practitionerRef(int $practitionerId, string $name): array
    {
        return ['reference' => 'Practitioner/'.$this->practitionerIds[$practitionerId], 'display' => $name];
    }

    /** @return array{reference: string, display: string} */
    private function encounterRef(): array
    {
        return ['reference' => $this->encounterUrl, 'display' => 'Kunjungan '.$this->visit->visit_number];
    }

    /**
     * Waktu dikirim dalam UTC (+00:00) sesuai katalog validasi SATUSEHAT: waktu lokal yang dikirim apa adanya
     * bisa dianggap "di masa depan" dan ditolak.
     */
    private static function time(?CarbonInterface $at): ?string
    {
        return $at?->copy()->utc()->format('Y-m-d\TH:i:sP');
    }

    private static function uuid(): string
    {
        return 'urn:uuid:'.Str::uuid()->toString();
    }
}
