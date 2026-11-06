<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ItemCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ItemCategory');
    }

    public function view(AuthUser $authUser, ItemCategory $itemCategory): bool
    {
        return $authUser->can('View:ItemCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ItemCategory');
    }

    public function update(AuthUser $authUser, ItemCategory $itemCategory): bool
    {
        return $authUser->can('Update:ItemCategory');
    }

    public function delete(AuthUser $authUser, ItemCategory $itemCategory): bool
    {
        return $authUser->can('Delete:ItemCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ItemCategory');
    }

    public function restore(AuthUser $authUser, ItemCategory $itemCategory): bool
    {
        return $authUser->can('Restore:ItemCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ItemCategory');
    }

}