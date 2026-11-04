<?php

namespace Database\Factories;

use App\Enums\TreatmentCategory;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Treatment> */
class TreatmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TD-###')),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(TreatmentCategory::cases()),
            'base_price' => fake()->numberBetween(5, 200) * 10_000,
            'duration_minutes' => 30,
            'is_active' => true,
        ];
    }
}
