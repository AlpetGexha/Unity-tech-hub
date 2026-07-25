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

    public function test_candidates_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('candidates'));
        $this->assertTrue(Schema::hasColumns('candidates', [
            'id', 'job_id', 'name', 'cv_path', 'github_username', 'linkedin_text', 'created_at', 'updated_at',
        ]));
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
