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
        $job = JobOpening::create([
            'title' => 'Laravel Developer (Junior / Mid-Level)',
            'requirements' => $this->realJobDescription(),
            'priorities' => $this->realJobPriorities(),
        ]);

        $candidates = collect($this->candidateData())
            ->map(fn (array $data): Candidate => Candidate::create([
                'job_id' => $job->id,
                'name' => $data['name'],
                'cv_markdown' => $data['cv_markdown'],
                'cv_path' => $data['cv_path'],
                'github_username' => $data['github_username'],
                'linkedin_text' => $data['linkedin_text'],
            ]));

        $candidates->each(function (Candidate $candidate): void {
            app(CandidateEvaluationService::class)->evaluate($candidate);
        });

        $this->verifyCacheServesWithoutLiveCalls($candidates);
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
