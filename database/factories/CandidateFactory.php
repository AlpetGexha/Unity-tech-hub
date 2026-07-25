<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => JobOpening::factory(),
            'name' => fake()->name(),
            'cv_path' => 'cvs/'.fake()->uuid().'.pdf',
            'github_username' => fake()->userName(),
            'linkedin_text' => fake()->paragraph(),
        ];
    }
}
