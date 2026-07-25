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
            'requirements' => $this->sentenceLines(5),
            'priorities' => $this->sentenceLines(3),
        ];
    }

    /**
     * Generate the given number of sentences, one per line.
     */
    protected function sentenceLines(int $count): string
    {
        return collect(range(1, $count))
            ->map(fn (): string => fake()->sentence())
            ->implode("\n");
    }
}
