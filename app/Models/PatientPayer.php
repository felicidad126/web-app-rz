<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kepesertaan penjamin milik pasien (nomor kartu BPJS/asuransi). */
#[Fillable(['patient_id', 'payer_id', 'member_number', 'valid_until'])]
class PatientPayer extends Model
{
    use LogsAudit;

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    protected function casts(): array
    {
        return ['valid_until' => 'date'];
    }
}
