<?php

namespace App\Http\Controllers;

use App\Ai\Agents\CandidateAnalyzer;
use App\Ai\Agents\CvMarkdownConverter;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\CandidateContext;
use App\Services\GitHubClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    /**
     * Show the form for adding a candidate to a role.
     */
    public function create(): Response
    {
        return Inertia::render('hr/candidate-form', [
            'jobs' => JobOpening::query()->orderBy('title')->get(['id', 'title']),
            'candidate' => session('candidate'),
        ]);
    }

    /**
     * Save a candidate and hand the form back the record so it can offer to analyse them.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_id' => ['required', 'exists:job_openings,id'],
            'name' => ['required', 'string', 'max:255'],
            'github_username' => ['required', 'string', 'max:255'],
            'linkedin_text' => ['nullable', 'string'],
            'cv_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $cvFile = $request->file('cv_file');

        $candidate = Candidate::create([
            ...Arr::except($validated, 'cv_file'),
            'cv_path' => $cvFile->store('cv'),
            'cv_markdown' => (new CvMarkdownConverter)->convert($cvFile),
        ]);

        return to_route('hr.candidates.create')->with('candidate', [
            'id' => $candidate->id,
            'name' => $candidate->name,
        ]);
    }

    /**
     * Show a candidate's most recent analysis.
     */
    public function show(Candidate $candidate): Response
    {
        return Inertia::render('hr/dashboard', [
            'analysis' => $candidate->analyses()->latest('id')->first()?->result,
        ]);
    }

    /**
     * Analyse a candidate against their role, reusing the stored result when nothing has changed.
     */
    public function analyze(Candidate $candidate): RedirectResponse
    {
        $job = $candidate->jobOpening;
        $inputHash = $this->inputHash($candidate, $job);

        if (! Analysis::where('input_hash', $inputHash)->exists()) {
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
        }

        return to_route('hr.candidates.show', $candidate);
    }

    /**
     * Fingerprint everything the analysis depends on, so editing any of it earns a fresh run.
     */
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
     * Source files come from the top repository only. Each repository costs four requests,
     * and an unauthenticated token budget is 60 an hour — the plan asks for 2-3 files, not
     * the widest possible sweep.
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
