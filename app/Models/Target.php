<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Target bulanan (produksi) per cabang, atau per dokter di cabang. Target harian = target ÷ hari kerja bulan itu.
 *
 * @property int $id
 * @property Carbon $period
 * @property int $branch_id
 * @property int|null $practitioner_id
 * @property string $metric
 * @property int $amount
 * @property-read Branch $branch
 * @property-read Practitioner|null $practitioner
 */
#[Fillable(['period', 'branch_id', 'practitioner_id', 'metric', 'amount'])]
class Target extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected bool $tracksUpdater = true;

    /** @var array<string, mixed> */
    protected $attributes = ['metric' => 'production'];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    protected function casts(): array
    {
        return ['period' => 'date', 'amount' => 'integer'];
    }
}
