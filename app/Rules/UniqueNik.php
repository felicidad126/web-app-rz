<?php

namespace App\Rules;

use App\Support\BlindIndex;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** NIK unik per tabel, dicek lewat blind index (termasuk data yang di-soft-delete). */
final class UniqueNik implements ValidationRule
{
    /** @param class-string<Model> $model */
    public function __construct(
        private readonly string $model,
        private readonly int|string|null $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        // Termasuk data yang di-soft-delete, agar NIK tidak bisa didaftarkan ulang diam-diam.
        $exists = $this->model::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('nik_hash', BlindIndex::hash((string) $value))
            ->when($this->ignoreId !== null, fn ($q) => $q->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('NIK ini sudah terdaftar.');
        }
    }
}
