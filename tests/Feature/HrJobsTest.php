<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HrJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roles_index_renders_existing_roles(): void
    {
        $firstRole = JobOpening::factory()->create(['title' => 'Backend Engineer']);
        $secondRole = JobOpening::factory()->create(['title' => 'Product Designer']);

        $this->get('/hr/jobs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/jobs')
                ->has('jobs', 2)
                ->where('jobs.0.id', $firstRole->id)
                ->where('jobs.1.id', $secondRole->id));
    }

    public function test_the_role_create_page_renders_an_empty_form(): void
    {
        $this->get('/hr/jobs/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/job-form')
                ->where('job', null));
    }

    public function test_a_role_can_be_viewed_with_its_candidates(): void
    {
        $role = JobOpening::factory()->create(['title' => 'Backend Engineer']);
        $candidate = Candidate::factory()->for($role, 'jobOpening')->create(['name' => 'Morgan Kelsey']);

        $this->get('/hr/jobs/'.$role->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/job-show')
                ->where('job.id', $role->id)
                ->where('job.title', 'Backend Engineer')
                ->has('job.candidates', 1)
                ->where('job.candidates.0.id', $candidate->id)
                ->where('job.candidates.0.name', 'Morgan Kelsey'));
    }

    public function test_the_role_edit_page_renders_the_selected_role(): void
    {
        $role = JobOpening::factory()->create([
            'title' => 'Backend Engineer',
            'requirements' => 'Laravel experience',
            'priorities' => 'Evidence over claims',
        ]);

        $this->get('/hr/jobs/'.$role->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/job-form')
                ->where('job.id', $role->id)
                ->where('job.title', 'Backend Engineer')
                ->where('job.requirements', 'Laravel experience')
                ->where('job.priorities', 'Evidence over claims'));
    }

    public function test_a_role_can_be_saved(): void
    {
        $response = $this->post('/hr/jobs', [
            'title' => 'Senior Laravel Engineer',
            'requirements' => "Five years of PHP.\nQueue experience.",
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);

        $response->assertRedirectToRoute('hr.jobs.index');

        $this->assertDatabaseHas('job_openings', [
            'title' => 'Senior Laravel Engineer',
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);
    }

    public function test_a_role_can_be_updated(): void
    {
        $role = JobOpening::factory()->create();

        $response = $this->put('/hr/jobs/'.$role->id, [
            'title' => 'Senior Laravel Engineer',
            'requirements' => 'Five years of PHP.',
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);

        $response->assertRedirectToRoute('hr.jobs.index');

        $this->assertDatabaseHas('job_openings', [
            'id' => $role->id,
            'title' => 'Senior Laravel Engineer',
            'requirements' => 'Five years of PHP.',
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);
    }

    public function test_view_and_edit_reject_a_missing_role(): void
    {
        $this->get('/hr/jobs/999999')->assertNotFound();
        $this->get('/hr/jobs/999999/edit')->assertNotFound();
        $this->put('/hr/jobs/999999', [
            'title' => 'Missing role',
            'requirements' => 'Anything',
            'priorities' => 'Anything',
        ])->assertNotFound();
    }

    public function test_an_existing_role_update_needs_a_title_requirements_and_priorities(): void
    {
        $role = JobOpening::factory()->create();

        $this->put('/hr/jobs/'.$role->id, [
            'title' => '',
            'requirements' => '',
            'priorities' => '',
        ])->assertSessionHasErrors(['title', 'requirements', 'priorities']);

        $this->assertModelExists($role);
    }

    public function test_a_role_needs_a_title_requirements_and_priorities(): void
    {
        $this->post('/hr/jobs', ['title' => '', 'requirements' => '', 'priorities' => ''])
            ->assertSessionHasErrors(['title', 'requirements', 'priorities']);

        $this->assertDatabaseCount('job_openings', 0);
    }

    public function test_the_rubric_turns_each_priority_into_a_weighted_criterion(): void
    {
        $job = JobOpening::factory()->create([
            'priorities' => "Verifiable delivery over claims.\nWrites tests.\nReads other people's code.",
            'requirements' => 'Five years of PHP.',
        ]);

        $rubric = $job->rubric();

        $this->assertCount(3, $rubric['criteria']);
        $this->assertSame('Verifiable delivery over claims.', $rubric['criteria'][0]['description']);
        $this->assertSame(100, array_sum(array_column($rubric['criteria'], 'weight')));
        $this->assertNotEmpty($rubric['scale']);

        foreach ($rubric['criteria'] as $criterion) {
            $this->assertNotEmpty($criterion['name']);
            $this->assertIsInt($criterion['weight']);
        }
    }

    public function test_the_rubric_falls_back_to_the_requirements_when_no_priorities_are_set(): void
    {
        $job = JobOpening::factory()->create([
            'priorities' => '   ',
            'requirements' => "Five years of PHP.\nQueue experience.",
        ]);

        $rubric = $job->rubric();

        $this->assertCount(2, $rubric['criteria']);
        $this->assertSame('Five years of PHP.', $rubric['criteria'][0]['description']);
        $this->assertSame(100, array_sum(array_column($rubric['criteria'], 'weight')));
    }

    public function test_the_rubric_still_returns_a_usable_criterion_for_an_empty_job(): void
    {
        $job = JobOpening::factory()->create(['priorities' => '', 'requirements' => '']);

        $rubric = $job->rubric();

        $this->assertCount(1, $rubric['criteria']);
        $this->assertSame(100, $rubric['criteria'][0]['weight']);
        $this->assertNotEmpty($rubric['criteria'][0]['description']);
    }
}
