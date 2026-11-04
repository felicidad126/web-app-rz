<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Practitioner;
use Illuminate\Auth\Access\HandlesAuthorization;

class PractitionerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Practitioner');
    }

    public function view(AuthUser $authUser, Practitioner $practitioner): bool
    {
        return $authUser->can('View:Practitioner');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Practitioner');
    }

    public function update(AuthUser $authUser, Practitioner $practitioner): bool
    {
        return $authUser->can('Update:Practitioner');
    }

    public function delete(AuthUser $authUser, Practitioner $practitioner): bool
    {
        return $authUser->can('Delete:Practitioner');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Practitioner');
    }

    public function restore(AuthUser $authUser, Practitioner $practitioner): bool
    {
        return $authUser->can('Restore:Practitioner');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Practitioner');
    }

}