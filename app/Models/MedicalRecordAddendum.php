<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Koreksi/tambahan setelah rekam medis dikunci. Hanya bisa ditambah.
 *
 * @property int $id
 * @property int $medical_record_id
 * @property string $content
 * @property int|null $created_by
 */
#[Fillable(['medical_record_id', 'content'])]
class MedicalRecordAddendum extends Model
{
    use LogsAudit;
    use RecordsAuthor;

    protected $table = 'medical_record_addenda';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Addendum tidak bisa diubah.'));
        static::deleting(fn () => throw new LogicException('Addendum tidak bisa dihapus.'));
    }

    /** @return BelongsTo<MedicalRecord, $this> */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
