<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LabOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class LabOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LabOrder');
    }

    public function view(AuthUser $authUser, LabOrder $labOrder): bool
    {
        return $authUser->can('View:LabOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LabOrder');
    }

    public function update(AuthUser $authUser, LabOrder $labOrder): bool
    {
        return $authUser->can('Update:LabOrder');
    }

    public function delete(AuthUser $authUser, LabOrder $labOrder): bool
    {
        return $authUser->can('Delete:LabOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LabOrder');
    }

    public function restore(AuthUser $authUser, LabOrder $labOrder): bool
    {
        return $authUser->can('Restore:LabOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LabOrder');
    }

}