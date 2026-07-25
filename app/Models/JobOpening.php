<?php

namespace App\Models;

use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $requirements
 * @property string $priorities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'requirements', 'priorities'])]
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
    use HasFactory;

    /**
     * The scoring bands, worded to match the rubric the analyzer is instructed with.
     */
    public const SCALE = '0-100 per criterion. 80+ is a strong yes, 60-79 a yes, 40-59 a maybe, below 40 a no.';

    /**
     * The criterion used when a role records neither priorities nor requirements.
     */
    public const DEFAULT_CRITERION = 'General software engineering ability, judged from verifiable work.';

    /**
     * Build the rubric echoed back in every analysis result.
     *
     * This is our own input reflected to the reader, never something the model generates,
     * so a report always shows what the candidate was actually scored against.
     *
     * @return array{criteria: array<int, array{name: string, weight: int, description: string}>, scale: string}
     */
    public function rubric(): array
    {
        $lines = $this->criterionLines();

        $weight = intdiv(100, count($lines));
        $remainder = 100 - ($weight * count($lines));

        $criteria = [];

        foreach (array_values($lines) as $index => $line) {
            $criteria[] = [
                'name' => Str::limit($line, 60),
                'weight' => $index === 0 ? $weight + $remainder : $weight,
                'description' => $line,
            ];
        }

        return ['criteria' => $criteria, 'scale' => self::SCALE];
    }

    /**
     * Get the lines the rubric criteria are built from, preferring what the team said it cares about.
     *
     * @return array<int, string>
     */
    protected function criterionLines(): array
    {
        foreach ([$this->priorities, $this->requirements] as $source) {
            $lines = Str::of((string) $source)
                ->explode("\n")
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->unique()
                ->values();

            if ($lines->isNotEmpty()) {
                return $lines->all();
            }
        }

        return [self::DEFAULT_CRITERION];
    }

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'job_id');
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}
