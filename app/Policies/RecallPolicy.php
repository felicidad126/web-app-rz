<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Recall;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecallPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Recall');
    }

    public function view(AuthUser $authUser, Recall $recall): bool
    {
        return $authUser->can('View:Recall');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Recall');
    }

    public function update(AuthUser $authUser, Recall $recall): bool
    {
        return $authUser->can('Update:Recall');
    }

    public function delete(AuthUser $authUser, Recall $recall): bool
    {
        return $authUser->can('Delete:Recall');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Recall');
    }

    public function restore(AuthUser $authUser, Recall $recall): bool
    {
        return $authUser->can('Restore:Recall');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Recall');
    }

}