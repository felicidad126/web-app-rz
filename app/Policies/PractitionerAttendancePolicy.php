<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PractitionerAttendance;
use Illuminate\Auth\Access\HandlesAuthorization;

class PractitionerAttendancePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PractitionerAttendance');
    }

    public function view(AuthUser $authUser, PractitionerAttendance $practitionerAttendance): bool
    {
        return $authUser->can('View:PractitionerAttendance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PractitionerAttendance');
    }

    public function update(AuthUser $authUser, PractitionerAttendance $practitionerAttendance): bool
    {
        return $authUser->can('Update:PractitionerAttendance');
    }

    public function delete(AuthUser $authUser, PractitionerAttendance $practitionerAttendance): bool
    {
        return $authUser->can('Delete:PractitionerAttendance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PractitionerAttendance');
    }

    public function restore(AuthUser $authUser, PractitionerAttendance $practitionerAttendance): bool
    {
        return $authUser->can('Restore:PractitionerAttendance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PractitionerAttendance');
    }

}