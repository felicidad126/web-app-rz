<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Item;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Item');
    }

    public function view(AuthUser $authUser, Item $item): bool
    {
        return $authUser->can('View:Item');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Item');
    }

    public function update(AuthUser $authUser, Item $item): bool
    {
        return $authUser->can('Update:Item');
    }

    public function delete(AuthUser $authUser, Item $item): bool
    {
        return $authUser->can('Delete:Item');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Item');
    }

    public function restore(AuthUser $authUser, Item $item): bool
    {
        return $authUser->can('Restore:Item');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Item');
    }

}