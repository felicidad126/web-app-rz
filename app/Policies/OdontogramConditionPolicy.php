<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OdontogramCondition;
use Illuminate\Auth\Access\HandlesAuthorization;

class OdontogramConditionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OdontogramCondition');
    }

    public function view(AuthUser $authUser, OdontogramCondition $odontogramCondition): bool
    {
        return $authUser->can('View:OdontogramCondition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OdontogramCondition');
    }

    public function update(AuthUser $authUser, OdontogramCondition $odontogramCondition): bool
    {
        return $authUser->can('Update:OdontogramCondition');
    }

    public function delete(AuthUser $authUser, OdontogramCondition $odontogramCondition): bool
    {
        return $authUser->can('Delete:OdontogramCondition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OdontogramCondition');
    }

    public function restore(AuthUser $authUser, OdontogramCondition $odontogramCondition): bool
    {
        return $authUser->can('Restore:OdontogramCondition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OdontogramCondition');
    }

}