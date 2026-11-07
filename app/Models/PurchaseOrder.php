<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Pesanan pembelian ke supplier. Memesan tidak menambah stok: stok bertambah saat barang diterima
 * (Purchasing::receive → StockLedger::receive, bisa sebagian).
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int|null $supplier_id
 * @property Carbon $order_date
 * @property Carbon|null $expected_at
 * @property PurchaseOrderStatus $status
 * @property int $total
 * @property int $paid_amount
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read Branch $branch
 * @property-read Supplier|null $supplier
 * @property-read User|null $creator
 */
#[Fillable(['number', 'branch_id', 'supplier_id', 'order_date', 'expected_at', 'status', 'total', 'paid_amount', 'notes', 'created_by', 'cancelled_by', 'cancelled_at', 'cancel_reason'])]
class PurchaseOrder extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'ordered', 'total' => 0, 'paid_amount' => 0];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }

    /** @return HasMany<PurchaseRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    /** @return HasMany<PurchaseCashEntry, $this> */
    public function cashEntries(): HasMany
    {
        return $this->hasMany(PurchaseCashEntry::class)->orderBy('id');
    }

    /** @return MorphMany<StockMovement, $this> penerimaan barang dari pesanan ini */
    public function receipts(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source')->orderBy('id');
    }

    public function unpaidAmount(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_at' => 'date',
            'cancelled_at' => 'datetime',
            'total' => 'integer',
            'paid_amount' => 'integer',
        ];
    }
}
