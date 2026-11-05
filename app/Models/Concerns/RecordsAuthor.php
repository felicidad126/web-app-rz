<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Mengisi created_by (dan updated_by bila model punya kolomnya) dari pengguna yang login.
 *
 * Model dengan kolom updated_by mendeklarasikan `protected bool $tracksUpdater = true;`.
 */
trait RecordsAuthor
{
    protected static function bootRecordsAuthor(): void
    {
        static::creating(function (Model $model): void {
            if (auth()->check() && $model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', auth()->id());
            }
        });

        static::saving(function (Model $model): void {
            if (auth()->check() && ($model->tracksUpdater ?? false)) {
                $model->setAttribute('updated_by', auth()->id());
            }
        });
    }
}
