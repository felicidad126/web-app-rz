<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StockOpname;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockOpnamePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockOpname');
    }

    public function view(AuthUser $authUser, StockOpname $stockOpname): bool
    {
        return $authUser->can('View:StockOpname');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockOpname');
    }

    public function update(AuthUser $authUser, StockOpname $stockOpname): bool
    {
        return $authUser->can('Update:StockOpname');
    }

    public function delete(AuthUser $authUser, StockOpname $stockOpname): bool
    {
        return $authUser->can('Delete:StockOpname');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StockOpname');
    }

    public function restore(AuthUser $authUser, StockOpname $stockOpname): bool
    {
        return $authUser->can('Restore:StockOpname');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockOpname');
    }

}