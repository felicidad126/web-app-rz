<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pemasok obat/bahan, termasuk lab gigi (`is_lab`).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_lab
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 * @property bool $is_active
 */
#[Fillable(['name', 'is_lab', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active'])]
class Supplier extends Model
{
    use LogsAudit;
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_lab' => 'boolean', 'is_active' => 'boolean'];
    }
}
