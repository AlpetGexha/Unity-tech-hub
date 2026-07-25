<?php

namespace Tests\Feature;

use App\Jobs\EvaluateCandidate;
use App\Jobs\ImportCandidatesFromCsv;
use App\Models\Candidate;
use App\Models\CandidateImport;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HrCandidateImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_upload_creates_an_import_and_dispatches_a_batch(): void
    {
        Bus::fake();
        Storage::fake('local');

        $job = JobOpening::factory()->create();

        $response = $this->post('/hr/candidate-imports', [
            'job_id' => $job->id,
            'csv_file' => UploadedFile::fake()->createWithContent(
                'candidates.csv',
                "name,cv_markdown,github_username,linkedin_text\nJamie Rivera,Backend engineer,jamierivera,Open to roles.\n"
            ),
        ]);

        $import = CandidateImport::firstOrFail();

        $response->assertRedirectToRoute('hr.candidate-imports.show', $import);
        $this->assertSame('pending', $import->status);
        Storage::disk('local')->assertExists($import->file_path);

        Bus::assertBatched(function ($batch) use ($import): bool {
            return $batch->name === 'Candidate import '.$import->id
                && $batch->hasJobs([ImportCandidatesFromCsv::class]);
        });
    }

    public function test_csv_import_rejects_missing_required_headers(): void
    {
        Bus::fake();
        Storage::fake('local');

        $job = JobOpening::factory()->create();

        $this->post('/hr/candidate-imports', [
            'job_id' => $job->id,
            'csv_file' => UploadedFile::fake()->createWithContent(
                'candidates.csv',
                "name,github_username\nJamie Rivera,jamierivera\n"
            ),
        ])->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('candidate_imports', 0);
        Bus::assertNothingBatched();
    }

    public function test_csv_upload_allows_github_and_linkedin_headers_to_be_omitted(): void
    {
        Bus::fake();
        Storage::fake('local');

        $job = JobOpening::factory()->create();

        $this->post('/hr/candidate-imports', [
            'job_id' => $job->id,
            'csv_file' => UploadedFile::fake()->createWithContent(
                'candidates.csv',
                "name,cv_markdown\nJamie Rivera,Backend engineer\n"
            ),
        ])->assertRedirect();

        Bus::assertBatched(fn ($batch): bool => $batch->hasJobs([ImportCandidatesFromCsv::class]));
    }

    public function test_import_job_creates_valid_candidates_and_queues_individual_evaluations(): void
    {
        Storage::fake('local');
        Bus::fake();

        $job = JobOpening::factory()->create();
        $import = CandidateImport::create([
            'job_id' => $job->id,
            'file_path' => 'imports/candidates.csv',
            'original_filename' => 'candidates.csv',
            'status' => 'pending',
            'failed_rows' => [],
        ]);

        Storage::disk('local')->put($import->file_path, implode("\n", [
            'name,cv_markdown',
            'Jamie Rivera,Backend engineer',
            'Missing Profiles,Backend engineer',
            'Taylor Stone,Platform engineer',
        ]));

        $jobInstance = new ImportCandidatesFromCsv($import->id);
        [$jobInstance, $batch] = $jobInstance->withFakeBatch();

        $jobInstance->handle();

        $this->assertDatabaseCount('candidates', 3);
        $this->assertDatabaseHas('candidates', [
            'name' => 'Jamie Rivera',
            'job_id' => $job->id,
            'cv_markdown' => 'Backend engineer',
        ]);
        $this->assertSame(3, count(array_filter($batch->added, fn (object $job): bool => $job instanceof EvaluateCandidate)));

        $import->refresh();
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(3, $import->valid_rows);
        $this->assertSame(0, $import->invalid_rows);
        $this->assertCount(0, $import->failed_rows);
    }

    public function test_import_status_page_exposes_batch_progress(): void
    {
        Bus::fake();

        $job = JobOpening::factory()->create();
        $batch = Bus::dispatchFakeBatch('Candidate import');
        $import = CandidateImport::create([
            'job_id' => $job->id,
            'file_path' => 'imports/candidates.csv',
            'original_filename' => 'candidates.csv',
            'batch_id' => $batch->id,
            'status' => 'running',
            'failed_rows' => [],
        ]);

        $this->get('/hr/candidate-imports/'.$import->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/candidate-import')
                ->where('import.id', $import->id)
                ->has('batch')
            );
    }

    public function test_a_failed_evaluation_marks_the_candidate_as_failed(): void
    {
        $candidate = Candidate::factory()->create([
            'evaluation_status' => 'running',
        ]);
        $job = new EvaluateCandidate($candidate->id);

        $job->failed(new \RuntimeException('The evaluator failed.'));

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'evaluation_status' => 'failed',
        ]);
    }
}
