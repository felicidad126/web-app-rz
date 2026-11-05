<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\Visit;
use Filament\Resources\Pages\CreateRecord;

class CreateTreatmentPlan extends CreateRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function afterFill(): void
    {
        // Dari halaman pemeriksaan: ?patient=..&visit=..
        $visit = request()->integer('visit') ? Visit::find(request()->integer('visit')) : null;
        $prefill = array_filter([
            'patient_id' => request()->integer('patient') ?: $visit?->patient_id,
            'visit_id' => $visit?->id,
            'practitioner_id' => $visit?->practitioner_id,
            'branch_id' => $visit?->branch_id,
        ], fn ($value): bool => filled($value));

        if ($prefill !== []) {
            $this->form->fill([...$this->data ?? [], ...$prefill]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => TreatmentPlanStatus::Draft, 'visit_id' => $data['visit_id'] ?? null];
    }
}
