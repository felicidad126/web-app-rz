<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Snapshot satu angka KPI per hari per cabang (dan per dokter bila relevan). Diisi App\Services\Reports\DailyMetrics.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $branch_id
 * @property int|null $practitioner_id
 * @property string $metric
 * @property int $value
 */
#[Fillable(['date', 'branch_id', 'practitioner_id', 'metric', 'value'])]
class DailyMetric extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'value' => 'integer'];
    }
}
