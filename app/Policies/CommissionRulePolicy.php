<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommissionRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommissionRulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommissionRule');
    }

    public function view(AuthUser $authUser, CommissionRule $commissionRule): bool
    {
        return $authUser->can('View:CommissionRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommissionRule');
    }

    public function update(AuthUser $authUser, CommissionRule $commissionRule): bool
    {
        return $authUser->can('Update:CommissionRule');
    }

    public function delete(AuthUser $authUser, CommissionRule $commissionRule): bool
    {
        return $authUser->can('Delete:CommissionRule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommissionRule');
    }

    public function restore(AuthUser $authUser, CommissionRule $commissionRule): bool
    {
        return $authUser->can('Restore:CommissionRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CommissionRule');
    }

}