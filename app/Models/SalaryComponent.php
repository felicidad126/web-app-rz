<?php

namespace App\Models;

use App\Enums\SalaryComponentKind;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Komponen gaji (gaji pokok, uang makan, tunjangan, potongan BPJS, PPh 21 manual, …).
 *
 * @property int $id
 * @property string $name
 * @property SalaryComponentKind $kind
 * @property int $default_amount
 * @property int $sort
 * @property bool $is_active
 */
#[Fillable(['name', 'kind', 'default_amount', 'sort', 'is_active'])]
class SalaryComponent extends Model
{
    use LogsAudit;
    use SoftDeletes;

    protected function casts(): array
    {
        return ['kind' => SalaryComponentKind::class, 'default_amount' => 'integer', 'sort' => 'integer', 'is_active' => 'boolean'];
    }
}
