<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PatientPackage;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPackagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PatientPackage');
    }

    public function view(AuthUser $authUser, PatientPackage $patientPackage): bool
    {
        return $authUser->can('View:PatientPackage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PatientPackage');
    }

    public function update(AuthUser $authUser, PatientPackage $patientPackage): bool
    {
        return $authUser->can('Update:PatientPackage');
    }

    public function delete(AuthUser $authUser, PatientPackage $patientPackage): bool
    {
        return $authUser->can('Delete:PatientPackage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PatientPackage');
    }

    public function restore(AuthUser $authUser, PatientPackage $patientPackage): bool
    {
        return $authUser->can('Restore:PatientPackage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PatientPackage');
    }

}