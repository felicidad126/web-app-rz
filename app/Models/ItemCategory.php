<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori obat & bahan untuk laporan (mis. Bahan restorasi, Anestesi, Habis pakai).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'is_active'])]
class ItemCategory extends Model
{
    use LogsAudit;
    use SoftDeletes;

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
