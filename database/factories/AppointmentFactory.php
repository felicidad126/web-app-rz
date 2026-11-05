<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = today()->setTime(9, 0);

        return [
            'branch_id' => Branch::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => Practitioner::factory(),
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(30),
            'status' => AppointmentStatus::Booked,
        ];
    }

    public function at(string $start, int $minutes = 30): static
    {
        return $this->state(fn () => [
            'start_at' => today()->setTimeFromTimeString($start),
            'end_at' => today()->setTimeFromTimeString($start)->addMinutes($minutes),
        ]);
    }
}
