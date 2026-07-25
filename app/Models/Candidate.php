<?php

namespace App\Models;

use App\CandidateInterviewStatus;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property string $name
 * @property string|null $cv_markdown
 * @property string|null $cv_path
 * @property string|null $github_username
 * @property string|null $linkedin_text
 * @property CandidateInterviewStatus|null $interview_status
 * @property string|null $evaluation_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_id', 'name', 'cv_markdown', 'cv_path', 'github_username', 'linkedin_text', 'interview_status', 'evaluation_status'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_status' => CandidateInterviewStatus::class,
        ];
    }

    /**
     * @return BelongsTo<JobOpening, $this>
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_id');
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * @return HasOne<Analysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(Analysis::class)->latestOfMany('id');
    }

    /**
     * Every candidate with their role and latest analysis, shaped for the
     * dashboard and HR candidates list views alike.
     *
     * @return array<int, array{id: int, name: string, job_title: string|null, fit_score: int|null, recommendation: string|null, analyzed_at: string|null, interview_status: string|null, can_start_ai_interview: bool}>
     */
    public static function evaluationOverview(): array
    {
        return static::query()
            ->with(['jobOpening:id,title', 'latestAnalysis'])
            ->orderBy('name')
            ->get()
            ->map(function (Candidate $candidate): array {
                $overall = $candidate->latestAnalysis?->result['overall'] ?? null;

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'job_title' => $candidate->jobOpening?->title,
                    'fit_score' => $overall['fit_score'] ?? null,
                    'recommendation' => $overall['recommendation'] ?? null,
                    'analyzed_at' => $candidate->latestAnalysis?->result['generated_at'] ?? null,
                    'interview_status' => $candidate->interview_status?->value,
                    'can_start_ai_interview' => $candidate->interview_status === CandidateInterviewStatus::Interviewed,
                ];
            })
            ->all();
    }
}
