<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kursi / dental unit di sebuah cabang. */
#[Fillable(['branch_id', 'name', 'is_active'])]
class Chair extends Model
{
    use HasFactory;
    use LogsAudit;

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
