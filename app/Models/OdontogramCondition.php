<?php

namespace App\Models;

use App\Enums\OdontogramCategory;
use App\Enums\OdontogramScope;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Master kode kondisi odontogram (bisa dikonfigurasi, bukan enum).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OdontogramCategory $category
 * @property OdontogramScope $applies_to
 * @property string|null $color
 * @property string|null $symbol
 * @property string|null $description
 * @property int $sort
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'category', 'applies_to', 'color', 'symbol', 'description', 'sort', 'is_active'])]
class OdontogramCondition extends Model
{
    use LogsAudit;

    protected function casts(): array
    {
        return [
            'category' => OdontogramCategory::class,
            'applies_to' => OdontogramScope::class,
            'is_active' => 'boolean',
        ];
    }
}
