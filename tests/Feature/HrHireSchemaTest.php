<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrHireSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_openings_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('job_openings'));
        $this->assertTrue(Schema::hasColumns('job_openings', [
            'id', 'title', 'requirements', 'priorities', 'created_at', 'updated_at',
        ]));
    }

    public function test_job_opening_factory_generates_one_requirement_and_priority_per_line(): void
    {
        $job = JobOpening::factory()->make();

        $this->assertCount(5, explode("\n", $job->requirements));
        $this->assertCount(3, explode("\n", $job->priorities));
        $this->assertNotEmpty(trim($job->title));
    }

    public function test_candidates_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('candidates'));
        $this->assertTrue(Schema::hasColumns('candidates', [
            'id', 'job_id', 'name', 'cv_markdown', 'cv_path', 'github_username', 'linkedin_text', 'created_at', 'updated_at',
        ]));
    }

    public function test_candidate_can_be_created_with_converted_cv_markdown_and_stored_pdf_path(): void
    {
        $job = JobOpening::factory()->create();

        $candidate = Candidate::create([
            'job_id' => $job->id,
            'name' => 'Jamie Rivera',
            'cv_markdown' => '# CV\n\nBackend engineer with 6 years of Laravel experience.',
            'cv_path' => 'cv/jamie-rivera.pdf',
            'github_username' => 'jamierivera',
            'linkedin_text' => null,
        ]);

        $reloaded = Candidate::find($candidate->id);

        $this->assertSame('# CV\n\nBackend engineer with 6 years of Laravel experience.', $reloaded->cv_markdown);
        $this->assertSame('cv/jamie-rivera.pdf', $reloaded->cv_path);
    }

    public function test_analyses_table_has_expected_columns_and_unique_input_hash(): void
    {
        $this->assertTrue(Schema::hasTable('analyses'));
        $this->assertTrue(Schema::hasColumns('analyses', [
            'id', 'candidate_id', 'job_id', 'input_hash', 'result', 'created_at', 'updated_at',
        ]));

        $job = JobOpening::factory()->create();
        $candidate = Candidate::factory()->create(['job_id' => $job->id]);

        Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'input_hash' => 'duplicate-hash',
            'result' => ['overall' => ['fit_score' => 50]],
        ]);

        $this->expectException(QueryException::class);

        Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'input_hash' => 'duplicate-hash',
            'result' => ['overall' => ['fit_score' => 60]],
        ]);
    }

    public function test_interviews_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('interviews'));
        $this->assertTrue(Schema::hasColumns('interviews', [
            'id', 'candidate_id', 'result', 'created_at', 'updated_at',
        ]));
    }

    public function test_analysis_result_casts_to_array(): void
    {
        $job = JobOpening::factory()->create();
        $candidate = Candidate::factory()->create(['job_id' => $job->id]);

        $nestedResult = [
            'overall' => ['fit_score' => 78, 'recommendation' => 'yes', 'summary' => 'Strong candidate.'],
            'skills' => [
                ['name' => 'Laravel', 'score' => 84, 'weight' => 40, 'reasoning' => 'Solid production repos.'],
            ],
            'claims' => [
                ['claim' => 'Built an API', 'status' => 'verified', 'evidence' => 'repo evidence', 'source' => 'repo', 'source_ref' => 'user/repo/file.php:10'],
            ],
        ];

        $analysis = Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'input_hash' => hash('sha256', 'unique-input'),
            'result' => $nestedResult,
        ]);

        $reloaded = Analysis::find($analysis->id);

        $this->assertIsArray($reloaded->result);
        $this->assertSame($nestedResult, $reloaded->result);
        $this->assertSame(78, $reloaded->result['overall']['fit_score']);
    }
}
