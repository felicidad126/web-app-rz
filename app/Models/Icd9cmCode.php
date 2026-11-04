<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Kode tindakan ICD-9-CM.
 *
 * @property string $code
 * @property string $name
 * @property string|null $name_id
 * @property-read string $label
 */
#[Fillable(['code', 'name', 'name_id', 'is_active'])]
class Icd9cmCode extends Model
{
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->code.' — '.($this->name_id ?: $this->name));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
