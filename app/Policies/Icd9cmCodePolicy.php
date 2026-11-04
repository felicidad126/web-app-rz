<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Icd9cmCode;
use Illuminate\Auth\Access\HandlesAuthorization;

class Icd9cmCodePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Icd9cmCode');
    }

    public function view(AuthUser $authUser, Icd9cmCode $icd9cmCode): bool
    {
        return $authUser->can('View:Icd9cmCode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Icd9cmCode');
    }

    public function update(AuthUser $authUser, Icd9cmCode $icd9cmCode): bool
    {
        return $authUser->can('Update:Icd9cmCode');
    }

    public function delete(AuthUser $authUser, Icd9cmCode $icd9cmCode): bool
    {
        return $authUser->can('Delete:Icd9cmCode');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Icd9cmCode');
    }

    public function restore(AuthUser $authUser, Icd9cmCode $icd9cmCode): bool
    {
        return $authUser->can('Restore:Icd9cmCode');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Icd9cmCode');
    }

}