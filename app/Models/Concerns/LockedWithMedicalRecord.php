<?php

namespace App\Models\Concerns;

use App\Exceptions\MedicalRecordLockedException;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;

/**
 * Data klinis milik sebuah kunjungan: tidak bisa ditambah, diubah, atau dihapus
 * setelah rekam medis kunjungan itu ditandatangani.
 */
trait LockedWithMedicalRecord
{
    abstract public function lockingVisit(): ?Visit;

    protected static function bootLockedWithMedicalRecord(): void
    {
        $guard = function (Model $model): void {
            /** @var self $model */
            if ($model->lockingVisit()?->isLocked()) {
                throw new MedicalRecordLockedException;
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }
}
