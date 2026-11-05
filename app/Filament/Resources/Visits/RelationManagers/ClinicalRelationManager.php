<?php

namespace App\Filament\Resources\Visits\RelationManagers;

use App\Models\Visit;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar tab klinis di halaman pemeriksaan: hanya terlihat oleh yang boleh membaca rekam medis,
 * dan hanya bisa diubah selama rekam medis belum dikunci.
 */
abstract class ClinicalRelationManager extends RelationManager
{
    /** Tetap bisa ditambah setelah rekam medis dikunci (lampiran, persetujuan). */
    protected static bool $editableAfterLock = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('View:MedicalRecord') ?? false;
    }

    public function isReadOnly(): bool
    {
        /** @var Visit $visit */
        $visit = $this->getOwnerRecord();

        return ! (auth()->user()?->can('Update:MedicalRecord') ?? false)
            || (! static::$editableAfterLock && $visit->isLocked());
    }
}
