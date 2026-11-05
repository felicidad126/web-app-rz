<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Package;
use Illuminate\Auth\Access\HandlesAuthorization;

class PackagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Package');
    }

    public function view(AuthUser $authUser, Package $package): bool
    {
        return $authUser->can('View:Package');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Package');
    }

    public function update(AuthUser $authUser, Package $package): bool
    {
        return $authUser->can('Update:Package');
    }

    public function delete(AuthUser $authUser, Package $package): bool
    {
        return $authUser->can('Delete:Package');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Package');
    }

    public function restore(AuthUser $authUser, Package $package): bool
    {
        return $authUser->can('Restore:Package');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Package');
    }

}