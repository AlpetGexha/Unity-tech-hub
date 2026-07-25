<?php

namespace Database\Seeders;

use App\Ai\Agents\CandidateAnalyzer;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\CandidateEvaluationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Seeds the real PRITECH Laravel role and the supplied Alpet Gexha CV, then
 * makes one real OpenAI call to warm the `analyses` cache.
 *
 * This is the only place in the whole application allowed to make a live call —
 * every other test and seeder uses `CandidateAnalyzer::fake()`. The candidates
 * below use real, public GitHub usernames so `GitHubClient` fetches real repos,
 * real trees, and real file contents, and so the CV claims below can genuinely
 * verify (or fail to) against that evidence.
 */
class DemoSeeder extends Seeder
{
    private const REAL_JOB_DESCRIPTION_PATH = 'demo/JobApplicationRecuirment.md';

    /**
     * Seed the demo job opening and candidates, then warm the analysis cache.
     */
    public function run(): void
    {
        $requirements = $this->realJobDescription();
        $priorities = $this->realJobPriorities();
        $job = JobOpening::firstOrCreate(
            [
                'title' => 'Laravel Developer (Junior / Mid-Level)',
                'requirements' => $requirements,
            ],
            ['priorities' => $priorities],
        );

        $candidates = collect($this->candidateData())
            ->map(fn (array $data): Candidate => $this->firstOrCreateCandidate($job, $data));

        $candidates->each(function (Candidate $candidate): void {
            app(CandidateEvaluationService::class)->evaluate($candidate);
        });

        $this->seedFakeEvaluations($job);
        $this->verifyCacheServesWithoutLiveCalls($candidates);
    }

    /**
     * Add a candidate without changing a matching record from an earlier demo run.
     *
     * @param  array{name: string, cv_markdown: string, cv_path: string|null, github_username: string, linkedin_text: string}  $data
     */
    protected function firstOrCreateCandidate(JobOpening $job, array $data): Candidate
    {
        return Candidate::firstOrCreate(
            ['job_id' => $job->id, 'name' => $data['name']],
            [
                'cv_markdown' => $data['cv_markdown'],
                'cv_path' => $data['cv_path'],
                'github_username' => $data['github_username'],
                'linkedin_text' => $data['linkedin_text'],
            ],
        );
    }

    /**
     * Seed fictional, already-evaluated candidates so the demo starts with a
     * useful range of outcomes without making extra provider calls.
     */
    protected function seedFakeEvaluations(JobOpening $job): void
    {
        foreach ($this->fakeEvaluationData() as $data) {
            $candidate = $this->firstOrCreateCandidate($job, [
                'name' => $data['name'],
                'cv_markdown' => $data['cv_markdown'],
                'cv_path' => null,
                'github_username' => '',
                'linkedin_text' => 'Demo profile data. No external profile was checked.',
            ]);

            $candidate->update(['evaluation_status' => 'completed']);

            Analysis::firstOrCreate(
                ['input_hash' => hash('sha256', 'demo-evaluation|'.$job->id.'|'.$candidate->id)],
                [
                    'candidate_id' => $candidate->id,
                    'job_id' => $job->id,
                    'result' => $this->fakeAnalysisResult($job, $candidate, $data),
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fakeAnalysisResult(JobOpening $job, Candidate $candidate, array $data): array
    {
        return [
            'candidate_id' => (string) $candidate->id,
            'job_id' => (string) $job->id,
            'generated_at' => $data['generated_at'],
            'cv_summary' => $data['cv_summary'],
            'overall' => $data['overall'],
            'skills' => $data['skills'],
            'claims' => $data['claims'],
            'interview_questions' => $data['interview_questions'],
            'rubric' => $job->rubric(),
            'fairness_note' => Analysis::FAIRNESS_NOTE,
        ];
    }

    /**
     * The supplied demo candidate and the markdown extracted from the provided PDF.
     *
     * @return array<int, array{name: string, cv_markdown: string, cv_path: string, github_username: string, linkedin_text: string}>
     */
    protected function candidateData(): array
    {
        return [
            [
                'name' => 'Alpet Gexha',
                'cv_path' => 'demo/CV Alpet Gexha.pdf',
                'github_username' => '',
                'cv_markdown' => <<<'TEXT'
                    # Alpet Gexha — Web Developer (TALL Stack)

                    ## Profile

                    Passionate full-stack developer with 3 years of hands-on experience
                    specializing in the TALL Stack (Tailwind, Alpine.js, Livewire, Laravel).
                    Experienced in designing, developing, and maintaining scalable web applications.
                    Also interested in cybersecurity and application security.

                    ## Experience

                    - Laravel Developer at Star Labs, Prishtina, July 2022 — September 2022.
                    - Built a full-function real-world e-ticket website using Laravel.
                    - Internship and training experience across web development, PHP, MySQL,
                      JavaScript, AWS, Python, and cybersecurity.

                    ## Projects

                    - Terminify appointments-management CRM SaaS with multi-tenancy, secure
                      payments, employee and customer management, auditing, analytics, and backups.
                    - Employee Management App with multi-company tenancy, data isolation, security,
                      onboarding, role assignment, payroll, and performance evaluation.
                    - E-Ticket platform with secure payments, event categorization, Google Maps,
                      user management, live chat, SEO, visitor tracking, and an admin panel.
                    - Invoice management, conference management, association, voting, and business
                      landing-page projects.

                    ## Education and additional experience

                    - Bachelor in Computer Science, University of Prizren Ukshin Hoti.
                    - Cybersecurity hackathons and CTFs, including second place at Secure the
                      Digital World in 2023.
                    - Training in Core Web Vitals, AWS, Python, cybersecurity, soft skills, machine
                      learning, reverse engineering, and multi-agent AI.
                    TEXT,
                'linkedin_text' => 'No LinkedIn profile text was supplied in the demo CV.',
            ],
        ];
    }

    /**
     * Fictional candidate profiles and hand-authored analysis results for the
     * dashboard demo. These records intentionally do not represent real people.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fakeEvaluationData(): array
    {
        return [
            [
                'name' => 'Maya Patel',
                'cv_markdown' => <<<'TEXT'
                    # Maya Patel - Laravel Engineer

                    Maya builds maintainable Laravel applications and has shipped
                    internal workflow tools, REST APIs, and automated test suites.
                    She enjoys turning ambiguous business processes into simple,
                    observable software.
                    TEXT,
                'generated_at' => '2026-07-22T10:00:00Z',
                'cv_summary' => 'Laravel engineer with strong backend experience across workflow tooling, REST APIs, and automated testing. Her examples show a practical approach to translating business processes into maintainable software.',
                'overall' => [
                    'fit_score' => 91,
                    'recommendation' => 'strong_yes',
                    'summary' => 'The supplied work demonstrates strong Laravel and relational-data fundamentals, backed by clear testing and delivery examples. The candidate appears ready to contribute with limited ramp-up time.',
                ],
                'skills' => [
                    [
                        'name' => 'PHP and Laravel',
                        'score' => 94,
                        'weight' => 40,
                        'reasoning' => 'The CV describes production Laravel workflow tools and shows a clear preference for maintainable framework conventions.',
                    ],
                    [
                        'name' => 'Testing and debugging',
                        'score' => 90,
                        'weight' => 30,
                        'reasoning' => 'Automated test suites and observable workflows are directly described in the candidate material.',
                    ],
                    [
                        'name' => 'Relational data and APIs',
                        'score' => 88,
                        'weight' => 30,
                        'reasoning' => 'REST API and workflow-tool experience provides relevant evidence for the role requirements.',
                    ],
                ],
                'claims' => [
                    [
                        'claim' => 'Built Laravel workflow tools.',
                        'status' => 'verified',
                        'evidence' => 'Maya builds maintainable Laravel applications and has shipped internal workflow tools, REST APIs, and automated test suites.',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                    [
                        'claim' => 'Uses automated testing in delivery.',
                        'status' => 'verified',
                        'evidence' => 'automated test suites',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                ],
                'interview_questions' => [
                    [
                        'question' => 'How would you structure a Laravel workflow so each business step remains observable and testable?',
                        'targets_skill' => 'Testing and debugging',
                        'grounded_in' => 'job_requirement',
                        'ideal_answer_points' => [
                            'Separates domain actions from controllers.',
                            'Uses focused feature tests around workflow transitions.',
                            'Adds logs or events that support diagnosis without exposing sensitive data.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Noah Williams',
                'cv_markdown' => <<<'TEXT'
                    # Noah Williams - Full-Stack Developer

                    Noah has delivered PHP applications for small teams, including
                    Laravel controllers, MySQL-backed reports, and integrations with
                    third-party services. His recent work has focused on improving
                    existing codebases and documenting deployment steps.
                    TEXT,
                'generated_at' => '2026-07-21T14:30:00Z',
                'cv_summary' => 'Full-stack developer with practical PHP and Laravel delivery experience, including MySQL reporting and third-party integrations. His profile suggests solid implementation ability with an opportunity to deepen testing and system-design practice.',
                'overall' => [
                    'fit_score' => 74,
                    'recommendation' => 'yes',
                    'summary' => 'The candidate shows relevant Laravel delivery experience and can likely contribute to the role. More evidence is needed around automated testing depth and designing larger backend boundaries.',
                ],
                'skills' => [
                    [
                        'name' => 'PHP and Laravel',
                        'score' => 78,
                        'weight' => 40,
                        'reasoning' => 'Laravel controllers and PHP application work are directly described, although the depth of framework ownership is not fully established.',
                    ],
                    [
                        'name' => 'Testing and debugging',
                        'score' => 62,
                        'weight' => 30,
                        'reasoning' => 'The profile emphasizes improving existing code, but gives limited concrete evidence of automated tests.',
                    ],
                    [
                        'name' => 'Relational data and APIs',
                        'score' => 80,
                        'weight' => 30,
                        'reasoning' => 'MySQL-backed reports and third-party integrations are relevant, concrete examples for this role.',
                    ],
                ],
                'claims' => [
                    [
                        'claim' => 'Worked with Laravel and MySQL.',
                        'status' => 'verified',
                        'evidence' => 'including Laravel controllers, MySQL-backed reports, and integrations with third-party services.',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                    [
                        'claim' => 'Has strong automated testing experience.',
                        'status' => 'unsupported',
                        'evidence' => '',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                ],
                'interview_questions' => [
                    [
                        'question' => 'What would you test first when extending a Laravel report backed by several relational queries?',
                        'targets_skill' => 'Testing and debugging',
                        'grounded_in' => 'job_requirement',
                        'ideal_answer_points' => [
                            'Covers expected business outcomes with feature tests.',
                            'Tests representative relationships and empty results.',
                            'Uses query inspection or profiling when performance matters.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Elena Rossi',
                'cv_markdown' => <<<'TEXT'
                    # Elena Rossi - Junior PHP Developer

                    Elena has completed several PHP learning projects and recently
                    contributed small fixes to a Laravel application. She is building
                    confidence with relational modelling, API design, and test-driven
                    development through guided practice.
                    TEXT,
                'generated_at' => '2026-07-20T09:15:00Z',
                'cv_summary' => 'Junior PHP developer with learning-project experience and early exposure to Laravel maintenance work. The profile shows motivation and growth potential, while production depth and independent delivery remain to be validated.',
                'overall' => [
                    'fit_score' => 48,
                    'recommendation' => 'maybe',
                    'summary' => 'The candidate has a promising foundation and willingness to learn, but the available evidence is light for a role requiring dependable Laravel delivery. A practical interview or take-home exercise would reduce the remaining uncertainty.',
                ],
                'skills' => [
                    [
                        'name' => 'PHP and Laravel',
                        'score' => 55,
                        'weight' => 40,
                        'reasoning' => 'PHP learning projects and small Laravel fixes show early exposure, but not yet sustained ownership of production features.',
                    ],
                    [
                        'name' => 'Testing and debugging',
                        'score' => 42,
                        'weight' => 30,
                        'reasoning' => 'Test-driven development is described as guided practice rather than independently demonstrated delivery.',
                    ],
                    [
                        'name' => 'Relational data and APIs',
                        'score' => 47,
                        'weight' => 30,
                        'reasoning' => 'Relational modelling and API design are stated learning areas without a concrete shipped example.',
                    ],
                ],
                'claims' => [
                    [
                        'claim' => 'Contributed fixes to a Laravel application.',
                        'status' => 'weak',
                        'evidence' => 'recently contributed small fixes to a Laravel application',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                    [
                        'claim' => 'Has shipped production API work.',
                        'status' => 'unsupported',
                        'evidence' => '',
                        'source' => 'cv',
                        'source_ref' => '',
                    ],
                ],
                'interview_questions' => [
                    [
                        'question' => 'Walk through how you would design and test a small Laravel endpoint from a written business requirement.',
                        'targets_skill' => 'Relational data and APIs',
                        'grounded_in' => 'job_requirement',
                        'ideal_answer_points' => [
                            'Clarifies inputs, authorization, and expected responses.',
                            'Models the data relationship before writing the query.',
                            'Adds a feature test for success and validation failure.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Read the real job description supplied for the demo.
     */
    protected function realJobDescription(): string
    {
        $path = public_path(self::REAL_JOB_DESCRIPTION_PATH);
        $description = file_get_contents($path);

        if ($description === false || trim($description) === '') {
            throw new RuntimeException("The demo job description is missing at [{$path}].");
        }

        return trim($description);
    }

    /**
     * Keep the role's priorities separate from its source job description so
     * the analyzer's rubric can weight the real hiring criteria explicitly.
     */
    protected function realJobPriorities(): string
    {
        return <<<'TEXT'
            Value potential, logic, work approach, and willingness to learn more than years alone.
            Prioritize PHP and Laravel, MVC, MySQL and relational data modelling, REST APIs,
            debugging, testing, Git, and translating business requirements into technical solutions.
            Value enterprise application experience, collaboration, communication, and maintainable
            backend code. Treat Livewire, Inertia, Vue, queues, jobs, caching, performance work,
            GitHub, personal projects, and open-source contributions as meaningful bonuses.
            TEXT;
    }

    /**
     * Prove the seeded analyses are served from the `analyses` table with zero
     * further prompts: prevent any stray prompt, then re-run the exact analyze()
     * path used above. If the cache key ever failed to match, analyze() would
     * try to prompt again and this would throw instead of silently re-billing.
     *
     * @param  Collection<int, Candidate>  $candidates
     */
    protected function verifyCacheServesWithoutLiveCalls($candidates): void
    {
        $before = Analysis::count();

        CandidateAnalyzer::fake()->preventStrayPrompts();

        $candidates->each(function (Candidate $candidate): void {
            app(CandidateEvaluationService::class)->evaluate($candidate);
        });

        $after = Analysis::count();

        if ($after !== $before) {
            throw new RuntimeException(
                'DemoSeeder cache verification failed: analyze() attempted a new prompt on the second read instead of reusing the cached analysis.'
            );
        }

        Log::info("DemoSeeder: cache verification passed, {$after} analyses total, no new prompt attempted on second read.");
    }
}
