<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Visit> */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => Practitioner::factory(),
            'visit_date' => today(),
            'status' => VisitStatus::Waiting,
        ];
    }

    public function inTreatment(): static
    {
        return $this->state(fn () => ['status' => VisitStatus::InTreatment, 'started_at' => now()]);
    }
}
