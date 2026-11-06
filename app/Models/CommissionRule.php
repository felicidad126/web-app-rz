<?php

namespace App\Models;

use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Enums\FeeGroup;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aturan jasa dokter/asisten. Dipilih dari yang paling spesifik (CommissionEngine::PRIORITY).
 * Perubahan aturan tidak mengubah tagihan yang sudah terbit: aturan di-snapshot per baris.
 *
 * @property int $id
 * @property string $name
 * @property int|null $practitioner_id
 * @property int|null $treatment_id
 * @property FeeGroup|null $fee_group
 * @property CommissionRole $role
 * @property CommissionType $type
 * @property string|null $percent
 * @property int|null $fixed_amount
 * @property CommissionBase $base
 * @property CommissionRecognition $recognition
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Practitioner|null $practitioner
 * @property-read Treatment|null $treatment
 */
#[Fillable(['name', 'practitioner_id', 'treatment_id', 'fee_group', 'role', 'type', 'percent', 'fixed_amount', 'base', 'recognition', 'is_active', 'notes'])]
class CommissionRule extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected bool $tracksUpdater = true;

    /** @var array<string, mixed> */
    protected $attributes = ['role' => 'operator', 'type' => 'percent', 'base' => 'net', 'recognition' => 'performed', 'is_active' => true];

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class)->withTrashed();
    }

    /** Ringkasan untuk tabel & slip, mis. "40% · Setelah diskon − biaya lab". */
    public function summary(): string
    {
        $value = $this->type === CommissionType::Percent
            ? rtrim(rtrim(number_format((float) $this->percent, 2, ',', '.'), '0'), ',').'%'
            : 'Rp '.number_format((int) $this->fixed_amount, 0, ',', '.').' per tindakan';

        return $this->type === CommissionType::Percent ? $value.' · '.$this->base->getLabel() : $value;
    }

    /** @return array<string, mixed> data yang disimpan di baris tagihan & entri jasa */
    public function snapshot(): array
    {
        return [
            'rule_id' => $this->id,
            'name' => $this->name,
            'role' => $this->role->value,
            'type' => $this->type->value,
            'percent' => $this->percent !== null ? (float) $this->percent : null,
            'fixed_amount' => $this->fixed_amount,
            'base' => $this->base->value,
            'recognition' => $this->recognition->value,
            'summary' => $this->summary(),
        ];
    }

    protected function casts(): array
    {
        return [
            'fee_group' => FeeGroup::class,
            'role' => CommissionRole::class,
            'type' => CommissionType::class,
            'percent' => 'decimal:2',
            'fixed_amount' => 'integer',
            'base' => CommissionBase::class,
            'recognition' => CommissionRecognition::class,
            'is_active' => 'boolean',
        ];
    }
}
