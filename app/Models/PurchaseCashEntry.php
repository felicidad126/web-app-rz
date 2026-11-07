<?php

namespace App\Models;

use App\Enums\PurchaseCashType;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Entri kas pembelian (rupiah bertanda: + masuk, − keluar). Tidak bisa diubah atau dihapus;
 * koreksi lewat entri pembalik (Purchasing::reverseCash). Saldo = jumlah amount per cabang.
 *
 * @property int $id
 * @property int $branch_id
 * @property Carbon $entry_date
 * @property PurchaseCashType $type
 * @property int $amount
 * @property string $description
 * @property int|null $purchase_order_id
 * @property int|null $reverses_entry_id
 * @property int|null $created_by
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read User|null $creator
 */
#[Fillable(['branch_id', 'entry_date', 'type', 'amount', 'description', 'purchase_order_id', 'reverses_entry_id', 'created_by'])]
class PurchaseCashEntry extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Entri kas pembelian tidak bisa diubah. Pakai koreksi.'));
        static::deleting(fn () => throw new LogicException('Entri kas pembelian tidak bisa dihapus. Pakai koreksi.'));
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<self, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    protected function casts(): array
    {
        return [
            'type' => PurchaseCashType::class,
            'entry_date' => 'date',
            'amount' => 'integer',
        ];
    }
}
