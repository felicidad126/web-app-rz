<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master paket perawatan (mis. behel + 12× kontrol).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $price
 * @property int|null $validity_days
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'price', 'validity_days', 'description', 'is_active'])]
class Package extends Model
{
    use LogsAudit;
    use SoftDeletes;

    /** @return HasMany<PackageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
