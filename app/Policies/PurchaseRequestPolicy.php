<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PurchaseRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PurchaseRequest');
    }

    public function view(AuthUser $authUser, PurchaseRequest $purchaseRequest): bool
    {
        return $authUser->can('View:PurchaseRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PurchaseRequest');
    }

    public function update(AuthUser $authUser, PurchaseRequest $purchaseRequest): bool
    {
        return $authUser->can('Update:PurchaseRequest');
    }

    public function delete(AuthUser $authUser, PurchaseRequest $purchaseRequest): bool
    {
        return $authUser->can('Delete:PurchaseRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PurchaseRequest');
    }

    public function restore(AuthUser $authUser, PurchaseRequest $purchaseRequest): bool
    {
        return $authUser->can('Restore:PurchaseRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PurchaseRequest');
    }

}