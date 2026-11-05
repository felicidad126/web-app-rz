<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanActions;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditTreatmentPlan extends EditRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    /** Isi rencana hanya bisa diubah sebelum pasien memutuskan. */
    public function isLocked(): bool
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return ! in_array($plan->status, [TreatmentPlanStatus::Draft, TreatmentPlanStatus::Presented], true);
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled($this->isLocked());
    }

    protected function getFormActions(): array
    {
        return $this->isLocked() ? [] : parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return TreatmentPlanActions::all();
    }

    public function getSubheading(): ?string
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return $plan->patient->name.' · '.$plan->status->getLabel()
            .($this->isLocked() ? ' · isi rencana tidak bisa diubah lagi; status item diperbarui otomatis saat tindakan ditandatangani.' : '');
    }
}
