<?php

namespace App\Models\Concerns;

use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Builder;

/**
 * NIK disimpan terenkripsi (kolom `nik`) + blind index (kolom `nik_hash`).
 *
 * Model wajib meng-cast `nik` sebagai `encrypted`.
 */
trait HasEncryptedNik
{
    public static function bootHasEncryptedNik(): void
    {
        static::saving(function (self $model): void {
            if ($model->isDirty('nik') || ($model->nik !== null && $model->nik_hash === null)) {
                $model->nik = BlindIndex::normalize($model->nik);
                $model->nik_hash = BlindIndex::hash($model->nik);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereNik(Builder $query, string $nik): Builder
    {
        return $query->where('nik_hash', BlindIndex::hash($nik));
    }

    public function maskedNik(): ?string
    {
        if (! $this->nik) {
            return null;
        }

        return substr($this->nik, 0, 4).str_repeat('•', max(strlen($this->nik) - 8, 0)).substr($this->nik, -4);
    }
}
