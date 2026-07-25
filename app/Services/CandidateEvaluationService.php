<?php

namespace App\Services;

use App\Ai\Agents\CandidateAnalyzer;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;

class CandidateEvaluationService
{
    /**
     * Analyse a candidate once for the current candidate and job inputs.
     */
    public function evaluate(Candidate $candidate): void
    {
        $job = $candidate->jobOpening;
        $inputHash = $this->inputHash($candidate, $job);

        if (Analysis::where('input_hash', $inputHash)->exists()) {
            $candidate->update(['evaluation_status' => 'completed']);

            return;
        }

        $context = CandidateContext::build($candidate, $this->gitHubEvidence($candidate));
        $result = (new CandidateAnalyzer($job))->analyse($context);

        Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'input_hash' => $inputHash,
            'result' => array_merge($result, [
                'candidate_id' => (string) $candidate->id,
                'job_id' => (string) $job->id,
                'generated_at' => now()->toIso8601String(),
                'rubric' => $job->rubric(),
                'fairness_note' => Analysis::FAIRNESS_NOTE,
            ]),
        ]);

        $candidate->update(['evaluation_status' => 'completed']);
    }

    protected function inputHash(Candidate $candidate, JobOpening $job): string
    {
        return hash('sha256', implode('|', [
            $job->id,
            $job->title,
            $job->requirements,
            $job->priorities,
            $candidate->id,
            $candidate->cv_markdown,
            $candidate->github_username,
            (string) $candidate->linkedin_text,
        ]));
    }

    /**
     * Gather the GitHub evidence the analyzer checks CV claims against.
     *
     * @return array{profile?: array<string, mixed>, repos?: array<int, array<string, mixed>>, sources?: array<int, array{path: string, content: string}>}
     */
    protected function gitHubEvidence(Candidate $candidate): array
    {
        $user = trim((string) $candidate->github_username);

        if ($user === '') {
            return [];
        }

        $repos = GitHubClient::topRepos($user);
        $topRepo = trim((string) ($repos[0]['name'] ?? ''));

        return [
            'profile' => GitHubClient::profile($user),
            'repos' => $repos,
            'sources' => $topRepo === '' ? [] : GitHubClient::sourceFiles($user, $topRepo),
        ];
    }
}
