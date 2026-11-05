<?php

namespace App\Models;

use App\Enums\AttachmentType;
use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Lampiran rekam medis (rontgen, foto, dokumen). File disimpan di disk privat.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property AttachmentType $type
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property Carbon|null $taken_at
 * @property string|null $notes
 * @property int|null $uploaded_by
 */
#[Fillable(['patient_id', 'visit_id', 'type', 'disk', 'path', 'original_name', 'taken_at', 'notes'])]
class Attachment extends Model
{
    use LogsAudit;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Attachment $attachment): void {
            $attachment->uploaded_by ??= auth()->id();
            $attachment->disk = $attachment->disk ?: 'local';
        });
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

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $this->path);
    }

    protected function casts(): array
    {
        return [
            'type' => AttachmentType::class,
            'taken_at' => 'date',
        ];
    }
}
