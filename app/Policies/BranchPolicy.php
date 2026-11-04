<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Branch;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Branch');
    }

    public function view(AuthUser $authUser, Branch $branch): bool
    {
        return $authUser->can('View:Branch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Branch');
    }

    public function update(AuthUser $authUser, Branch $branch): bool
    {
        return $authUser->can('Update:Branch');
    }

    public function delete(AuthUser $authUser, Branch $branch): bool
    {
        return $authUser->can('Delete:Branch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Branch');
    }

    public function restore(AuthUser $authUser, Branch $branch): bool
    {
        return $authUser->can('Restore:Branch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Branch');
    }

}