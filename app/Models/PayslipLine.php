<?php

namespace App\Models;

use App\Enums\SalaryComponentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Baris slip gaji (nama komponen di-snapshot). Terkunci setelah slip final.
 *
 * @property int $id
 * @property int $payslip_id
 * @property int|null $salary_component_id
 * @property SalaryComponentKind $kind
 * @property string $name
 * @property int $amount
 * @property int $sort
 */
#[Fillable(['payslip_id', 'salary_component_id', 'kind', 'name', 'amount', 'sort'])]
class PayslipLine extends Model
{
    protected static function booted(): void
    {
        $guard = function (PayslipLine $line): void {
            $status = Payslip::query()->whereKey($line->payslip_id)->toBase()->value('status');

            if ($status !== null && $status !== 'draft') {
                throw new LogicException('Slip gaji yang sudah final tidak bisa diubah.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Payslip, $this> */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    protected function casts(): array
    {
        return ['kind' => SalaryComponentKind::class, 'amount' => 'integer', 'sort' => 'integer'];
    }
}
