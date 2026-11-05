<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConsentTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsentTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConsentTemplate');
    }

    public function view(AuthUser $authUser, ConsentTemplate $consentTemplate): bool
    {
        return $authUser->can('View:ConsentTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConsentTemplate');
    }

    public function update(AuthUser $authUser, ConsentTemplate $consentTemplate): bool
    {
        return $authUser->can('Update:ConsentTemplate');
    }

    public function delete(AuthUser $authUser, ConsentTemplate $consentTemplate): bool
    {
        return $authUser->can('Delete:ConsentTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ConsentTemplate');
    }

    public function restore(AuthUser $authUser, ConsentTemplate $consentTemplate): bool
    {
        return $authUser->can('Restore:ConsentTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConsentTemplate');
    }

}