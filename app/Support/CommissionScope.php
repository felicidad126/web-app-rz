<?php

namespace App\Support;

use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Jasa & rekap: owner/admin melihat semua (ViewAll:Commission); dokter hanya miliknya (Planning §4.3). */
final class CommissionScope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $column = 'practitioner_id'): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->can('ViewAll:Commission')) {
            return $query;
        }

        return $query->where($column, self::ownPractitionerId($user) ?? 0);
    }

    public static function ownPractitionerId(?User $user): ?int
    {
        return $user === null ? null : Practitioner::query()->where('user_id', $user->id)->value('id');
    }

    public static function canViewPractitioner(?User $user, int $practitionerId): bool
    {
        return $user !== null && ($user->can('ViewAll:Commission') || self::ownPractitionerId($user) === $practitionerId);
    }
}
