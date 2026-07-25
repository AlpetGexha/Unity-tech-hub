<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * HR's dedicated candidates roster, alongside the general dashboard overview.
 */
class HrCandidatesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hr_candidates_route_carries_no_auth_or_verified_middleware(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn (RoutingRoute $route): bool => $route->getName() === 'hr.candidates.index');

        $this->assertNotNull($route, 'No route named [hr.candidates.index] is registered.');

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains('auth', $middleware);
        $this->assertNotContains('verified', $middleware);
    }

    public function test_a_guest_can_open_the_hr_candidates_page(): void
    {
        $this->get('/hr/candidates')->assertOk();
    }

    public function test_the_hr_candidates_page_lists_candidates_with_their_role_and_analysis_state(): void
    {
        $job = JobOpening::factory()->create(['title' => 'Senior Laravel Engineer']);

        $analyzed = Candidate::factory()->create(['job_id' => $job->id, 'name' => 'Jamie Rivera']);
        Analysis::create([
            'candidate_id' => $analyzed->id,
            'job_id' => $job->id,
            'input_hash' => hash('sha256', 'analyzed'),
            'result' => [
                'overall' => ['fit_score' => 82, 'recommendation' => 'yes', 'summary' => 'Strong.'],
                'generated_at' => '2026-07-01T00:00:00Z',
            ],
        ]);

        Candidate::factory()->create(['job_id' => $job->id, 'name' => 'Alex Chen']);

        $this->get('/hr/candidates')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/candidates')
                ->has('candidates', 2)
                ->where('candidates.0.name', 'Alex Chen')
                ->where('candidates.0.job_title', 'Senior Laravel Engineer')
                ->where('candidates.0.fit_score', null)
                ->where('candidates.0.recommendation', null)
                ->where('candidates.1.name', 'Jamie Rivera')
                ->where('candidates.1.fit_score', 82)
                ->where('candidates.1.recommendation', 'yes')
                ->where('candidates.1.analyzed_at', '2026-07-01T00:00:00Z')
            );
    }

    public function test_the_hr_candidates_page_shows_an_empty_state_with_no_candidates(): void
    {
        $this->get('/hr/candidates')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/candidates')
                ->where('candidates', [])
            );
    }
}
