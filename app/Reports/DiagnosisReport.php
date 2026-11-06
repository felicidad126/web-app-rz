<?php

namespace App\Reports;

use App\Enums\VisitStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Top diagnosa ICD-10 (laporan Dinkes): jumlah kasus & pasien. */
class DiagnosisReport extends Report
{
    public static function key(): string
    {
        return 'diagnosa';
    }

    public static function label(): string
    {
        return 'Diagnosis terbanyak';
    }

    public static function description(): string
    {
        return 'Diagnosa ICD-10 dari rekam medis kunjungan yang tidak dibatalkan, menurut tanggal kunjungan.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports');
    }

    public function columns(): array
    {
        return [['label' => 'Kode', 'type' => 'text'], ['label' => 'Diagnosa', 'type' => 'text'], ['label' => 'Kasus', 'type' => 'number'], ['label' => 'Pasien', 'type' => 'number']];
    }

    public function rows(): array
    {
        return DB::table('diagnoses')
            ->join('medical_records', 'medical_records.id', '=', 'diagnoses.medical_record_id')
            ->join('visits', 'visits.id', '=', 'medical_records.visit_id')
            ->leftJoin('icd10_codes', 'icd10_codes.code', '=', 'diagnoses.icd10_code')
            ->whereNull('diagnoses.deleted_at')
            ->where('visits.status', '!=', VisitStatus::Cancelled->value)
            ->whereIn('visits.branch_id', $this->filters->branchIds)
            ->whereDate('visits.visit_date', '>=', $this->filters->from->toDateString())->whereDate('visits.visit_date', '<=', $this->filters->to->toDateString())
            ->groupBy('diagnoses.icd10_code', 'icd10_codes.name', 'icd10_codes.name_id')
            ->selectRaw('diagnoses.icd10_code as code, coalesce(icd10_codes.name_id, icd10_codes.name) as name, count(*) as cases, count(distinct visits.patient_id) as patients')
            ->orderByDesc('cases')->limit(50)->get()
            ->map(fn ($r): array => [$r->code, (string) ($r->name ?? ''), (int) $r->cases, (int) $r->patients])->all();
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), [2]);
    }
}
