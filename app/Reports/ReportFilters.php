<?php

namespace App\Reports;

use Illuminate\Support\Carbon;

/** Filter laporan: rentang tanggal, cabang, dokter (opsional), dan pengelompokan (opsional). */
final class ReportFilters
{
    /** @param list<int> $branchIds */
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly array $branchIds,
        public readonly ?int $practitionerId = null,
        public readonly ?string $group = null,
    ) {}

    public function periodLabel(): string
    {
        return $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y');
    }
}
