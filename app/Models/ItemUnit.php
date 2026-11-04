<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Konversi satuan: 1 {unit} = {factor_to_base} satuan dasar. */
#[Fillable(['item_id', 'unit_id', 'factor_to_base'])]
class ItemUnit extends Model
{
    use LogsAudit;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    protected function casts(): array
    {
        return ['factor_to_base' => 'decimal:4'];
    }
}
