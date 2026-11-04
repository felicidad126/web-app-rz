<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??#')),
            'name' => 'Cabang '.fake()->city(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
