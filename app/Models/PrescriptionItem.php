<?php

namespace App\Models;

use App\Models\Concerns\LockedWithMedicalRecord;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $prescription_id
 * @property int $item_id
 * @property string|null $kfa_code
 * @property string $qty
 * @property int $unit_id
 * @property string $signa
 * @property string|null $notes
 */
#[Fillable(['prescription_id', 'item_id', 'kfa_code', 'qty', 'unit_id', 'signa', 'notes'])]
class PrescriptionItem extends Model
{
    use LockedWithMedicalRecord;
    use LogsAudit;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (PrescriptionItem $line): void {
            $line->kfa_code ??= $line->item?->kfa_code;
        });
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lockingVisit(): ?Visit
    {
        return $this->prescription?->visit;
    }

    protected function casts(): array
    {
        return ['qty' => 'decimal:2'];
    }
}
