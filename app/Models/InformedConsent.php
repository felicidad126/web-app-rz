<?php

namespace App\Models;

use App\Enums\SignerRelation;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Persetujuan tindakan yang sudah ditandatangani. Isi di-snapshot; tidak bisa diubah.
 *
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property int|null $consent_template_id
 * @property int $practitioner_id
 * @property string $title
 * @property string $content_snapshot
 * @property string $signer_name
 * @property SignerRelation $signer_relation
 * @property string $signature_path
 * @property string|null $pdf_path
 * @property Carbon $signed_at
 */
#[Fillable([
    'visit_id', 'patient_id', 'consent_template_id', 'practitioner_id', 'title', 'content_snapshot',
    'signer_name', 'signer_relation', 'signature_path', 'pdf_path', 'signed_at',
])]
class InformedConsent extends Model
{
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    /** @var list<string> */
    protected array $auditExcept = ['content_snapshot'];

    protected static function booted(): void
    {
        static::updating(function (InformedConsent $consent): void {
            // Satu-satunya perubahan yang boleh: melengkapi path PDF setelah dibuat.
            if (array_diff(array_keys($consent->getDirty()), ['pdf_path', 'updated_at']) !== []) {
                throw new LogicException('Persetujuan yang sudah ditandatangani tidak bisa diubah.');
            }
        });
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<ConsentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id');
    }

    protected function casts(): array
    {
        return [
            'signer_relation' => SignerRelation::class,
            'signed_at' => 'datetime',
        ];
    }
}
