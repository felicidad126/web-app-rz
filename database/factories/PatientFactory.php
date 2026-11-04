<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'name' => fake()->name($gender === Gender::Male ? 'male' : 'female'),
            'nik' => fake()->unique()->numerify('3171############'),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-75 years', '-3 years'),
            'gender' => $gender,
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'allergies' => fake()->optional(0.15)->randomElement(['Penisilin', 'Amoksisilin', 'Lidokain', 'Lateks', 'Ibuprofen']),
            'systemic_diseases' => fake()->optional(0.2)->randomElement(['Hipertensi', 'Diabetes melitus tipe 2', 'Asma', 'Kelainan jantung']),
            'consent_data_at' => now(),
        ];
    }

    public function withoutNik(): static
    {
        return $this->state(fn (array $attributes) => ['nik' => null]);
    }
}
