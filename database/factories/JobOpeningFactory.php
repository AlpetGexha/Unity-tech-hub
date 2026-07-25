<?php

namespace Database\Factories;

use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'requirements' => implode("\n", fake()->sentences(5)),
            'priorities' => implode("\n", fake()->sentences(3)),
        ];
    }
}
