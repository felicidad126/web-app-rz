<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payer;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payer');
    }

    public function view(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('View:Payer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payer');
    }

    public function update(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Update:Payer');
    }

    public function delete(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Delete:Payer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Payer');
    }

    public function restore(AuthUser $authUser, Payer $payer): bool
    {
        return $authUser->can('Restore:Payer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Payer');
    }

}