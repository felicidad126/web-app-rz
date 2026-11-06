<?php

namespace App\Models;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Baris tagihan. Harga, diskon, pajak, dan (nanti) aturan komisi di-snapshot di baris ini.
 *
 * @property int $id
 * @property int $invoice_id
 * @property InvoiceItemType $item_type
 * @property int|null $visit_procedure_id
 * @property int|null $treatment_id
 * @property int|null $item_id
 * @property int|null $unit_id
 * @property int|null $prescription_item_id
 * @property int|null $patient_package_id
 * @property int|null $installment_id
 * @property string $description
 * @property string|null $tooth_number
 * @property string|null $surfaces
 * @property string $qty
 * @property int $unit_price
 * @property int $item_discount
 * @property string|null $item_discount_percent
 * @property int $allocated_invoice_discount
 * @property string $tax_rate
 * @property int $tax
 * @property int $net_amount
 * @property int $package_value
 * @property int|null $practitioner_id
 * @property int|null $assistant_id
 * @property int $material_cost
 * @property int $lab_cost
 * @property array<string, mixed>|null $commission_rule_snapshot
 * @property int $sort
 */
#[Fillable([
    'invoice_id', 'item_type', 'visit_procedure_id', 'treatment_id', 'item_id', 'unit_id', 'prescription_item_id',
    'patient_package_id', 'installment_id', 'description', 'tooth_number', 'surfaces', 'qty', 'unit_price',
    'item_discount', 'item_discount_percent', 'allocated_invoice_discount', 'tax_rate', 'tax', 'net_amount', 'package_value',
    'practitioner_id', 'assistant_id', 'material_cost', 'lab_cost', 'commission_rule_snapshot', 'sort',
])]
class InvoiceItem extends Model
{
    use LogsAudit;

    /** @var array<string, mixed> */
    protected $attributes = [
        'qty' => 1, 'unit_price' => 0, 'item_discount' => 0, 'allocated_invoice_discount' => 0, 'tax_rate' => 0,
        'tax' => 0, 'net_amount' => 0, 'package_value' => 0, 'material_cost' => 0, 'lab_cost' => 0, 'sort' => 0,
    ];

    protected static function booted(): void
    {
        $guard = function (InvoiceItem $item): void {
            $status = Invoice::query()->whereKey($item->invoice_id)->toBase()->value('status');

            if ($status !== null && $status !== InvoiceStatus::Draft->value) {
                throw new LogicException('Baris tagihan terkunci setelah tagihan diterbitkan.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<VisitProcedure, $this> */
    public function visitProcedure(): BelongsTo
    {
        return $this->belongsTo(VisitProcedure::class);
    }

    /** @return BelongsTo<PrescriptionItem, $this> */
    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    /** @return BelongsTo<PatientPackage, $this> */
    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class);
    }

    /** @return BelongsTo<Installment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** Jumlah baris setelah diskon item (sebelum alokasi diskon invoice & pajak). */
    public function lineSubtotal(): int
    {
        return (int) round((float) $this->qty * $this->unit_price) - $this->item_discount;
    }

    public function qtyLabel(): string
    {
        $qty = (float) $this->qty;

        return fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2, ',', ''), '0'), ',');
    }

    protected function casts(): array
    {
        return [
            'item_type' => InvoiceItemType::class,
            'qty' => 'decimal:2',
            'unit_price' => 'integer',
            'item_discount' => 'integer',
            'item_discount_percent' => 'decimal:2',
            'allocated_invoice_discount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax' => 'integer',
            'net_amount' => 'integer',
            'package_value' => 'integer',
            'material_cost' => 'integer',
            'lab_cost' => 'integer',
            'commission_rule_snapshot' => 'array',
            'sort' => 'integer',
        ];
    }
}
