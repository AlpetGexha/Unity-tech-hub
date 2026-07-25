<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HrCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_candidate_form_receives_the_roles_it_can_be_scored_against(): void
    {
        JobOpening::factory()->create(['title' => 'Senior Laravel Engineer']);
        JobOpening::factory()->create(['title' => 'Data Engineer']);

        $this->get('/hr/candidates/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/candidate-form')
                ->has('jobs', 2)
                ->has('jobs.0', fn (AssertableInertia $job) => $job->has('id')->has('title'))
            );
    }

    public function test_a_saved_candidate_comes_back_as_a_prop_so_the_analyse_button_can_render(): void
    {
        $job = JobOpening::factory()->create();

        $this->post('/hr/candidates', [
            'job_id' => $job->id,
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'github_username' => 'jamierivera',
            'linkedin_text' => 'Open to backend roles.',
        ])->assertRedirectToRoute('hr.candidates.create');

        $this->assertDatabaseHas('candidates', [
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
        ]);

        $this->get('/hr/candidates/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('candidate', fn (AssertableInertia $candidate) => $candidate
                    ->has('id')
                    ->where('name', 'Jamie Rivera')
                )
            );
    }

    public function test_the_saved_candidate_prop_is_gone_on_the_next_visit(): void
    {
        $job = JobOpening::factory()->create();

        $this->post('/hr/candidates', [
            'job_id' => $job->id,
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'github_username' => 'jamierivera',
        ]);

        $this->get('/hr/candidates/create');

        $this->get('/hr/candidates/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('candidate', null));
    }

    public function test_an_unknown_role_is_rejected_before_it_reaches_the_foreign_key(): void
    {
        $this->post('/hr/candidates', [
            'job_id' => 999,
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'github_username' => 'jamierivera',
        ])->assertSessionHasErrors('job_id');

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_a_candidate_needs_a_role_a_name_and_a_cv(): void
    {
        $this->post('/hr/candidates', [])
            ->assertSessionHasErrors(['job_id', 'name', 'cv_text']);

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_an_uploaded_cv_is_stored_and_recorded_on_the_candidate(): void
    {
        Storage::fake('local');

        $job = JobOpening::factory()->create();

        $this->post('/hr/candidates', [
            'job_id' => $job->id,
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'github_username' => 'jamierivera',
            'cv_file' => UploadedFile::fake()->create('jamie-rivera.pdf', 12, 'application/pdf'),
        ])->assertRedirectToRoute('hr.candidates.create');

        $candidate = Candidate::firstOrFail();

        $this->assertNotNull($candidate->cv_path);
        Storage::disk('local')->assertExists($candidate->cv_path);
    }

    public function test_the_candidate_page_renders_the_dashboard_with_no_analysis_yet(): void
    {
        $candidate = Candidate::factory()->create();

        $this->get('/hr/candidates/'.$candidate->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/dashboard')
                ->where('analysis', null)
            );
    }

    public function test_the_candidate_page_renders_the_stored_analysis(): void
    {
        $candidate = Candidate::factory()->create();

        Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $candidate->job_id,
            'input_hash' => hash('sha256', 'stored'),
            'result' => ['overall' => ['fit_score' => 78, 'recommendation' => 'yes', 'summary' => 'Strong.']],
        ]);

        $this->get('/hr/candidates/'.$candidate->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/dashboard')
                ->where('analysis.overall.fit_score', 78)
            );
    }
}
