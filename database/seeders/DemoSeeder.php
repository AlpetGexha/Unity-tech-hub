<?php

namespace Database\Seeders;

use App\Ai\Agents\CandidateAnalyzer;
use App\Http\Controllers\CandidateController;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Seeds one job opening and three real-world candidates, then makes one real
 * OpenAI call per candidate to warm the `analyses` cache.
 *
 * This is the only place in the whole application allowed to make a live call —
 * every other test and seeder uses `CandidateAnalyzer::fake()`. The candidates
 * below use real, public GitHub usernames so `GitHubClient` fetches real repos,
 * real trees, and real file contents, and so the CV claims below can genuinely
 * verify (or fail to) against that evidence.
 */
class DemoSeeder extends Seeder
{
    /**
     * Seed the demo job opening and candidates, then warm the analysis cache.
     */
    public function run(): void
    {
        $job = JobOpening::create([
            'title' => 'Senior JavaScript/TypeScript Platform Engineer',
            'requirements' => <<<'TEXT'
                5+ years building and maintaining production JavaScript/TypeScript libraries or frameworks.
                Deep understanding of the language runtime: module systems, async patterns, type systems.
                A track record of open-source library design that other developers built on top of.
                Comfortable reading and reviewing unfamiliar codebases quickly.
                Strong testing discipline and public API design instincts.
                TEXT,
            'priorities' => <<<'TEXT'
                We weight demonstrated, shipped code over self-reported experience.
                Small, well-tested, dependency-light libraries are a stronger signal than large monorepos.
                Clear public API design and documentation quality matter as much as raw feature count.
                TEXT,
        ]);

        $candidates = collect($this->candidateData())
            ->map(fn (array $data): Candidate => Candidate::create([
                'job_id' => $job->id,
                'name' => $data['name'],
                'cv_text' => $data['cv_text'],
                'cv_path' => null,
                'github_username' => $data['github_username'],
                'linkedin_text' => $data['linkedin_text'],
            ]));

        $candidates->each(function (Candidate $candidate): void {
            app(CandidateController::class)->analyze($candidate);
        });

        $this->verifyCacheServesWithoutLiveCalls($candidates);
    }

    /**
     * The demo candidates: real, public GitHub maintainers whose highest-starred
     * owned repository is real source code (not a curated list), so the analyzer
     * has genuine files to check the CV claims against.
     *
     * @return array<int, array{name: string, github_username: string, cv_text: string, linkedin_text: string}>
     */
    protected function candidateData(): array
    {
        return [
            [
                'name' => 'TJ Holowaychuk',
                'github_username' => 'tj',
                'cv_text' => <<<'TEXT'
                    Node.js and JavaScript tooling engineer. Creator of widely-adopted open-source
                    libraries, including a command-line argument-parsing library used across the
                    Node ecosystem, plus several Shell and Node CLI utilities. Deep experience
                    designing small, composable APIs that other developers build on top of.
                    Comfortable across JavaScript, Go, and Shell.
                    TEXT,
                'linkedin_text' => 'Open-source engineer, ex-Apex/Auth0. Focused on developer tooling and CLIs.',
            ],
            [
                'name' => 'Jason Miller',
                'github_username' => 'developit',
                'cv_text' => <<<'TEXT'
                    Front-end and library engineer, creator of the Preact UI library and several
                    small, dependency-free JavaScript/TypeScript utilities, including a tiny event
                    emitter used by many other packages. Strong focus on bundle size, TypeScript
                    typings, and minimal public APIs. Currently a DX engineer at Shopify.
                    TEXT,
                'linkedin_text' => 'DX Engineer at Shopify. Creator of @preactjs. Prefers small, fast libraries.',
            ],
            [
                'name' => 'Jeremy Ashkenas',
                'github_username' => 'jashkenas',
                'cv_text' => <<<'TEXT'
                    JavaScript language and framework engineer. Created foundational front-end
                    libraries adopted industry-wide, including an MVC framework and a widely used
                    utility-belt library, plus a language that compiles to JavaScript. Deep
                    experience with API design that has stayed stable across a decade of
                    production use.
                    TEXT,
                'linkedin_text' => 'Creator of Backbone.js, Underscore.js, and CoffeeScript.',
            ],
        ];
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
            app(CandidateController::class)->analyze($candidate);
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
