<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Services\CandidateEvaluationService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EvaluateCandidate implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $candidateId) {}

    /**
     * Execute the job.
     */
    public function handle(CandidateEvaluationService $evaluationService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $candidate = Candidate::findOrFail($this->candidateId);
        $candidate->update(['evaluation_status' => 'running']);

        $evaluationService->evaluate($candidate);
    }

    public function failed(Throwable $exception): void
    {
        Candidate::whereKey($this->candidateId)->update(['evaluation_status' => 'failed']);
    }
}
