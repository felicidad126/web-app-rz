<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penjamin pembayaran: umum, BPJS, asuransi, corporate.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PayerType $type
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'type', 'phone', 'notes', 'is_active'])]
class Payer extends Model
{
    use HasFactory;
    use LogsAudit;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PayerType::class,
            'is_active' => 'boolean',
        ];
    }
}
