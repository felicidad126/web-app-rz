<?php

namespace Database\Factories;

use App\Enums\PractitionerType;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Practitioner> */
class PractitionerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => PractitionerType::Dentist,
            'nik' => fake()->unique()->numerify('3273############'),
            'str_number' => fake()->numerify('##########'),
            'sip_number' => fake()->bothify('SIP/###/DG/????/2025'),
            'sip_expired_at' => now()->addYears(2),
        ];
    }

    public function sipExpiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => ['sip_expired_at' => now()->addDays($days)]);
    }
}
