<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'address', 'phone', 'satusehat_location_id', 'is_active'])]
class Branch extends Model
{
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    /** @return HasMany<Chair, $this> */
    public function chairs(): HasMany
    {
        return $this->hasMany(Chair::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
