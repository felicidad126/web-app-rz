<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StockMovement;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockMovementPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockMovement');
    }

    public function view(AuthUser $authUser, StockMovement $stockMovement): bool
    {
        return $authUser->can('View:StockMovement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockMovement');
    }

    public function update(AuthUser $authUser, StockMovement $stockMovement): bool
    {
        return $authUser->can('Update:StockMovement');
    }

    public function delete(AuthUser $authUser, StockMovement $stockMovement): bool
    {
        return $authUser->can('Delete:StockMovement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StockMovement');
    }

    public function restore(AuthUser $authUser, StockMovement $stockMovement): bool
    {
        return $authUser->can('Restore:StockMovement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockMovement');
    }

}