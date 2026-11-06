<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Concerns\LogsAudit;
use App\Models\Concerns\RecordsAuthor;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Kunjungan pasien (dari janji temu atau datang langsung).
 *
 * @property int $id
 * @property string $visit_number
 * @property int $branch_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $chair_id
 * @property int|null $appointment_id
 * @property int|null $payer_id
 * @property Carbon $visit_date
 * @property int $queue_number
 * @property VisitStatus $status
 * @property bool $is_walk_in
 * @property string|null $chief_complaint
 * @property Carbon|null $arrived_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 * @property-read MedicalRecord|null $medicalRecord
 */
#[Fillable([
    'branch_id', 'patient_id', 'practitioner_id', 'chair_id', 'appointment_id', 'payer_id', 'visit_date',
    'queue_number', 'status', 'is_walk_in', 'chief_complaint', 'arrived_at', 'started_at', 'finished_at', 'cancelled_at',
])]
class Visit extends Model
{
    use HasFactory;
    use LogsAudit;
    use RecordsAuthor;
    use SoftDeletes;

    protected bool $tracksUpdater = true;

    protected static function booted(): void
    {
        static::creating(function (Visit $visit): void {
            $visit->visit_date ??= now();
            $visit->arrived_at ??= now();
            $date = Carbon::parse($visit->visit_date);

            if (blank($visit->visit_number)) {
                $visit->visit_number = NumberSequence::nextVisitNumber($date);
            }

            if (blank($visit->queue_number)) {
                $visit->queue_number = NumberSequence::next('queue', $visit->branch_id.'-'.$date->format('Ymd'));
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

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Chair, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(Chair::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /** @return HasOne<MedicalRecord, $this> */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /** @return HasOne<OralExamination, $this> */
    public function oralExamination(): HasOne
    {
        return $this->hasOne(OralExamination::class);
    }

    /** @return HasMany<VisitProcedure, $this> */
    public function procedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class);
    }

    /** @return HasMany<OdontogramEntry, $this> */
    public function odontogramEntries(): HasMany
    {
        return $this->hasMany(OdontogramEntry::class);
    }

    /** @return HasOne<Prescription, $this> */
    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** @return HasMany<InformedConsent, $this> */
    public function informedConsents(): HasMany
    {
        return $this->hasMany(InformedConsent::class);
    }

    /** @return HasManyThrough<MedicalRecordAddendum, MedicalRecord, $this> */
    public function addenda(): HasManyThrough
    {
        return $this->hasManyThrough(MedicalRecordAddendum::class, MedicalRecord::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return MorphMany<SatuSehatSync, $this> */
    public function satusehatSyncs(): MorphMany
    {
        return $this->morphMany(SatuSehatSync::class, 'syncable');
    }

    /** Tagihan kunjungan yang berlaku (bukan void). */
    public function activeInvoice(): ?Invoice
    {
        return $this->invoices()->where('status', '!=', InvoiceStatus::Void)->latest('id')->first();
    }

    /** Rekam medis kunjungan, dibuat otomatis saat pertama dibuka. */
    public function ensureMedicalRecord(): MedicalRecord
    {
        return $this->medicalRecord()->firstOrCreate([], ['patient_id' => $this->patient_id]);
    }

    public function isLocked(): bool
    {
        return $this->medicalRecord?->signed_at !== null;
    }

    public function waitingMinutes(): ?int
    {
        if ($this->arrived_at === null) {
            return null;
        }

        return (int) $this->arrived_at->diffInMinutes($this->started_at ?? now());
    }

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'queue_number' => 'integer',
            'status' => VisitStatus::class,
            'is_walk_in' => 'boolean',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
