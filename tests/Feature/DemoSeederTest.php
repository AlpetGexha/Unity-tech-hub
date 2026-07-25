<?php

namespace Tests\Feature;

use App\Ai\Agents\CandidateAnalyzer;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_uses_the_real_pritech_role_and_alpet_candidate(): void
    {
        CandidateAnalyzer::fake();
        Http::fake();

        $this->seed(DemoSeeder::class);

        $job = JobOpening::firstOrFail();

        $this->assertSame('Laravel Developer (Junior / Mid-Level)', $job->title);
        $this->assertStringContainsString(
            'PRITECH is looking for a Laravel Developer',
            $job->requirements,
        );
        $this->assertSame(4, Candidate::count());
        $this->assertDatabaseHas('candidates', [
            'job_id' => $job->id,
            'name' => 'Alpet Gexha',
        ]);
        $this->assertDatabaseHas('candidates', [
            'job_id' => $job->id,
            'name' => 'Maya Patel',
        ]);
        $this->assertDatabaseHas('candidates', [
            'job_id' => $job->id,
            'name' => 'Noah Williams',
        ]);
        $this->assertDatabaseHas('candidates', [
            'job_id' => $job->id,
            'name' => 'Elena Rossi',
        ]);
        $this->assertSame(4, Analysis::count());

        $this->assertStringContainsString(
            $job->requirements,
            (string) (new CandidateAnalyzer($job))->instructions(),
        );
        CandidateAnalyzer::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('Alpet Gexha')
        );
    }

    public function test_demo_seeding_is_additive_and_does_not_duplicate_demo_records(): void
    {
        CandidateAnalyzer::fake();
        Http::fake();

        $this->seed(DemoSeeder::class);
        $existingCandidate = Candidate::factory()->create(['name' => 'Existing Candidate']);

        $this->seed(DemoSeeder::class);

        $this->assertModelExists($existingCandidate);
        $this->assertSame(5, Candidate::count());
        $this->assertSame(2, JobOpening::count());
        $this->assertSame(4, Analysis::count());
        $this->assertSame(1, Candidate::where('name', 'Maya Patel')->count());
    }
}
