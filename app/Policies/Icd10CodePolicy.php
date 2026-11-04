<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Icd10Code;
use Illuminate\Auth\Access\HandlesAuthorization;

class Icd10CodePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Icd10Code');
    }

    public function view(AuthUser $authUser, Icd10Code $icd10Code): bool
    {
        return $authUser->can('View:Icd10Code');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Icd10Code');
    }

    public function update(AuthUser $authUser, Icd10Code $icd10Code): bool
    {
        return $authUser->can('Update:Icd10Code');
    }

    public function delete(AuthUser $authUser, Icd10Code $icd10Code): bool
    {
        return $authUser->can('Delete:Icd10Code');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Icd10Code');
    }

    public function restore(AuthUser $authUser, Icd10Code $icd10Code): bool
    {
        return $authUser->can('Restore:Icd10Code');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Icd10Code');
    }

}