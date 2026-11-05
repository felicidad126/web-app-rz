<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use App\Services\Billing\PackageSales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Tagihan pasien. Draf bisa diubah; setelah diterbitkan baris & angka terkunci (koreksi lewat void).
 *
 * @property int $id
 * @property string|null $invoice_number
 * @property int $branch_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $payer_id
 * @property InvoiceStatus $status
 * @property int $subtotal
 * @property int $invoice_discount
 * @property string|null $invoice_discount_percent
 * @property int $tax_total
 * @property int $rounding
 * @property int $total
 * @property int $payer_portion
 * @property int $patient_portion
 * @property int $paid_total
 * @property Carbon|null $issued_at
 * @property int|null $issued_by
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property string|null $void_reason
 * @property int|null $void_requested_by
 * @property Carbon|null $void_requested_at
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 */
#[Fillable([
    'branch_id', 'patient_id', 'visit_id', 'payer_id', 'status', 'subtotal', 'invoice_discount', 'invoice_discount_percent',
    'tax_total', 'rounding', 'total', 'payer_portion', 'patient_portion', 'paid_total', 'notes',
])]
class Invoice extends Model
{
    use HasFactory;
    use LogsAudit;
    use RecordsAuthor;

    protected bool $tracksUpdater = true;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft', 'subtotal' => 0, 'invoice_discount' => 0, 'tax_total' => 0, 'rounding' => 0,
        'total' => 0, 'payer_portion' => 0, 'patient_portion' => 0, 'paid_total' => 0,
    ];

    /** Kolom yang membentuk nilai tagihan: hanya boleh berubah selama draf. */
    public const AMOUNT_COLUMNS = [
        'patient_id', 'visit_id', 'payer_id', 'subtotal', 'invoice_discount', 'invoice_discount_percent',
        'tax_total', 'rounding', 'total', 'payer_portion', 'patient_portion',
    ];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $original = $invoice->getOriginal('status');
            $wasDraft = $original === InvoiceStatus::Draft || $original === InvoiceStatus::Draft->value;

            if (! $wasDraft && $invoice->isDirty(self::AMOUNT_COLUMNS)) {
                throw new LogicException('Tagihan yang sudah diterbitkan tidak bisa diubah. Ajukan void lalu buat tagihan baru.');
            }
        });
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Tagihan yang masih punya sisa (sudah terbit, belum lunas). */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial]);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::Partial], true);
    }

    /** Sisa yang harus dibayar pasien. */
    public function balance(): int
    {
        if (in_array($this->status, [InvoiceStatus::Void, InvoiceStatus::Draft], true)) {
            return $this->status === InvoiceStatus::Draft ? $this->patient_portion : 0;
        }

        return max(0, $this->patient_portion - $this->paid_total);
    }

    /** Hitung ulang total dibayar & status dari pembayaran aktif dan refund yang sudah diserahkan. */
    public function syncPaymentStatus(): void
    {
        if (in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            return;
        }

        $paid = (int) $this->payments()->where('status', '!=', PaymentStatus::Void)->sum('amount')
            - (int) $this->refunds()->where('status', RefundStatus::Paid)->sum('amount');
        $paid = max(0, $paid);

        $status = match (true) {
            $paid >= $this->patient_portion => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Issued,
        };

        $this->forceFill([
            'paid_total' => $paid,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? ($this->paid_at ?? now()) : null,
        ])->save();

        foreach ($this->installments()->with('patientPackage')->get() as $installment) {
            $installment->syncFromInvoice($this);
            PackageSales::completeIfDone($installment->patientPackage);
        }
    }

    public function label(): string
    {
        return $this->invoice_number ?? 'Draf #'.$this->id;
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'integer',
            'invoice_discount' => 'integer',
            'invoice_discount_percent' => 'decimal:2',
            'tax_total' => 'integer',
            'rounding' => 'integer',
            'total' => 'integer',
            'payer_portion' => 'integer',
            'patient_portion' => 'integer',
            'paid_total' => 'integer',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'void_requested_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
