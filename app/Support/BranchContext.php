<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Cabang tempat pengguna sedang bekerja (disimpan di session).
 *
 * Owner/Admin bisa memilih semua cabang aktif; peran lain hanya cabang yang ditugaskan.
 */
final class BranchContext
{
    private const SESSION_KEY = 'clinic.branch_id';

    /** @return Collection<int, Branch> */
    public static function available(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $query = Branch::query()->where('is_active', true)->orderBy('name');

        if (! $user->hasAnyRole(['owner', 'admin', config('filament-shield.super_admin.name')])) {
            $query->whereIn('id', $user->branches()->pluck('branches.id'));
        }

        return $query->get();
    }

    public static function id(): ?int
    {
        $available = self::available();
        $selected = session(self::SESSION_KEY);

        if ($selected && $available->contains('id', $selected)) {
            return (int) $selected;
        }

        return $available->first()?->id;
    }

    public static function set(int $branchId): void
    {
        if (self::available()->contains('id', $branchId)) {
            session([self::SESSION_KEY => $branchId]);
        }
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return self::available()->pluck('name', 'id')->all();
    }
}
