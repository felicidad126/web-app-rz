<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TreatmentPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentPlanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentPlan');
    }

    public function view(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('View:TreatmentPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TreatmentPlan');
    }

    public function update(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Update:TreatmentPlan');
    }

    public function delete(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Delete:TreatmentPlan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TreatmentPlan');
    }

    public function restore(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Restore:TreatmentPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TreatmentPlan');
    }

}