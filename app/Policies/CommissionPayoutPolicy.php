<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommissionPayout;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommissionPayoutPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommissionPayout');
    }

    public function view(AuthUser $authUser, CommissionPayout $commissionPayout): bool
    {
        return $authUser->can('View:CommissionPayout');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommissionPayout');
    }

    public function update(AuthUser $authUser, CommissionPayout $commissionPayout): bool
    {
        return $authUser->can('Update:CommissionPayout');
    }

    public function delete(AuthUser $authUser, CommissionPayout $commissionPayout): bool
    {
        return $authUser->can('Delete:CommissionPayout');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommissionPayout');
    }

    public function restore(AuthUser $authUser, CommissionPayout $commissionPayout): bool
    {
        return $authUser->can('Restore:CommissionPayout');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CommissionPayout');
    }

}