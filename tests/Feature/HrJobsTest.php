<?php

namespace Tests\Feature;

use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HrJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_role_form_renders(): void
    {
        $this->get('/hr/jobs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('hr/jobs'));
    }

    public function test_a_role_can_be_saved(): void
    {
        $response = $this->post('/hr/jobs', [
            'title' => 'Senior Laravel Engineer',
            'requirements' => "Five years of PHP.\nQueue experience.",
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);

        $response->assertRedirectToRoute('hr.candidates.create');

        $this->assertDatabaseHas('job_openings', [
            'title' => 'Senior Laravel Engineer',
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);
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
