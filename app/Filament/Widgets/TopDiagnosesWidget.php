<?php

namespace App\Filament\Widgets;

use App\Enums\VisitStatus;
use App\Support\BranchContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** Top-10 diagnosa ICD-10 bulan ini (bahan laporan Dinkes). */
class TopDiagnosesWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.simple-table';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = DB::table('diagnoses')
            ->join('medical_records', 'medical_records.id', '=', 'diagnoses.medical_record_id')
            ->join('visits', 'visits.id', '=', 'medical_records.visit_id')
            ->leftJoin('icd10_codes', 'icd10_codes.code', '=', 'diagnoses.icd10_code')
            ->whereNull('diagnoses.deleted_at')
            ->where('visits.status', '!=', VisitStatus::Cancelled->value)
            ->whereIn('visits.branch_id', BranchContext::available()->pluck('id'))
            ->whereDate('visits.visit_date', '>=', now()->startOfMonth()->toDateString())
            ->groupBy('diagnoses.icd10_code', 'icd10_codes.name', 'icd10_codes.name_id')
            ->selectRaw('diagnoses.icd10_code as code, coalesce(icd10_codes.name_id, icd10_codes.name) as name, count(*) as total')
            ->orderByDesc('total')->limit(10)->get();

        return [
            'heading' => 'Top-10 diagnosa bulan berjalan',
            'description' => 'ICD-10, semua kunjungan yang tidak dibatalkan',
            'columns' => [['label' => 'Kode'], ['label' => 'Diagnosa'], ['label' => 'Kasus', 'align' => 'end']],
            'rows' => $rows->map(fn ($r): array => [['text' => $r->code, 'mono' => true], (string) ($r->name ?? '—'), (string) $r->total])->all(),
            'empty' => 'Belum ada diagnosa bulan berjalan.',
        ];
    }
}
