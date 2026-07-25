<?php

namespace App\Http\Controllers;

use App\Ai\Agents\CandidatePdfIntake;
use App\Ai\Agents\CvMarkdownConverter;
use App\CandidateInterviewStatus;
use App\Jobs\EvaluateCandidate;
use App\Jobs\ImportCandidatesFromCsv;
use App\Models\Candidate;
use App\Models\CandidateImport;
use App\Models\JobOpening;
use App\Services\CandidateEvaluationService;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CandidateController extends Controller
{
    protected const CV_PREVIEW_CACHE_PREFIX = 'candidate-cv-preview:';

    /**
     * Show every candidate with their role and latest analysis.
     */
    public function index(): Response
    {
        return Inertia::render('hr/candidates', ['candidates' => Candidate::evaluationOverview()]);
    }

    /**
     * Show the form for adding a candidate to a role.
     */
    public function create(): Response
    {
        return Inertia::render('hr/candidate-form', [
            'jobs' => JobOpening::query()->orderBy('title')->get(['id', 'title']),
            'candidate' => session('candidate'),
        ]);
    }

    /**
     * Convert an uploaded PDF and extract profile fields for the candidate form.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cv_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        /** @var UploadedFile $cvFile */
        $cvFile = $validated['cv_file'];
        $extracted = (new CandidatePdfIntake)->extract($cvFile);
        $profile = [
            'name' => trim($extracted['name']),
            'github_username' => trim($extracted['github_username']),
            'linkedin_text' => trim($extracted['linkedin_text']),
        ];
        $previewToken = Str::random(64);

        Cache::put($this->previewCacheKey($previewToken), [
            'file_hash' => $this->fileHash($cvFile),
        ], now()->addMinutes(15));

        return response()->json([
            'preview_token' => $previewToken,
            'profile' => $profile,
        ]);
    }

    /**
     * Save a candidate, queue its AI evaluation, and open the result page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_id' => ['required', 'exists:job_openings,id'],
            'name' => ['required', 'string', 'max:255'],
            'github_username' => ['required', 'string', 'max:255'],
            'linkedin_text' => ['nullable', 'string'],
            'cv_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'cv_preview_token' => ['nullable', 'string', 'size:64'],
        ]);

        $cvFile = $request->file('cv_file');

        /** @var UploadedFile $cvFile */
        $cvMarkdown = $this->cvMarkdown($validated['cv_preview_token'] ?? null, $cvFile);

        $candidate = Candidate::create([
            ...Arr::except($validated, ['cv_file', 'cv_preview_token']),
            'cv_path' => $cvFile->store('cv'),
            'cv_markdown' => $cvMarkdown,
            'evaluation_status' => 'pending',
        ]);

        EvaluateCandidate::dispatch($candidate->id);

        return to_route('hr.candidates.show', $candidate);
    }

    /**
     * Show a candidate's most recent analysis.
     */
    public function show(Candidate $candidate): Response
    {
        $analysis = $candidate->analyses()->latest('id')->first();

        return Inertia::render('hr/dashboard', [
            'analysis' => $analysis?->result,
            'evaluationStatus' => $candidate->evaluation_status,
        ]);
    }

    /**
     * Analyse a candidate against their role, reusing the stored result when nothing has changed.
     */
    public function analyze(Candidate $candidate, CandidateEvaluationService $evaluationService): RedirectResponse
    {
        $evaluationService->evaluate($candidate);

        return to_route('hr.candidates.show', $candidate);
    }

    /**
     * Update the candidate's interview pipeline status.
     */
    public function updateStatus(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate([
            'interview_status' => ['nullable', 'string', Rule::enum(CandidateInterviewStatus::class)],
        ]);

        $candidate->update($validated);

        return to_route('hr.candidates.index');
    }

    /**
     * Queue a CSV candidate import for one selected role.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_id' => ['required', 'exists:job_openings,id'],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv_file');
        $headers = $this->csvHeaders($file->getRealPath());
        $requiredHeaders = ['name', 'cv_markdown', 'github_username'];

        if (array_diff($requiredHeaders, $headers) !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV must include these headers: '.implode(', ', $requiredHeaders).'.',
            ]);
        }

        $candidateImport = CandidateImport::create([
            'job_id' => $validated['job_id'],
            'file_path' => $file->store('candidate-imports'),
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'pending',
            'failed_rows' => [],
        ]);

        $importId = $candidateImport->id;
        $batch = Bus::batch([new ImportCandidatesFromCsv($importId)])
            ->name('Candidate import '.$importId)
            ->allowFailures()
            ->before(function (Batch $batch) use ($importId): void {
                CandidateImport::whereKey($importId)->update(['status' => 'running']);
            })
            ->progress(function (Batch $batch) use ($importId): void {
                CandidateImport::whereKey($importId)->update([
                    'status' => $batch->failedJobs > 0 ? 'failed' : 'running',
                ]);
            })
            ->then(function (Batch $batch) use ($importId): void {
                CandidateImport::whereKey($importId)->update(['status' => 'completed']);
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($importId): void {
                CandidateImport::whereKey($importId)->update(['status' => 'failed']);
            })
            ->finally(function (Batch $batch) use ($importId): void {
                $import = CandidateImport::find($importId);

                if ($import !== null) {
                    $import->update([
                        'status' => $batch->failedJobs > 0 ? 'failed' : 'completed',
                    ]);
                }
            })
            ->dispatch();

        $candidateImport->update(['batch_id' => $batch->id]);

        return to_route('hr.candidate-imports.show', $candidateImport);
    }

    /**
     * Show the progress page for a CSV candidate import.
     */
    public function importShow(CandidateImport $candidateImport): Response
    {
        return Inertia::render('hr/candidate-import', [
            'import' => $candidateImport,
            'batch' => $this->batchStatus($candidateImport),
        ]);
    }

    /**
     * Return the current queue batch progress for polling.
     */
    public function importStatus(CandidateImport $candidateImport): JsonResponse
    {
        return response()->json([
            'import' => $candidateImport->fresh(),
            'batch' => $this->batchStatus($candidateImport),
        ]);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    protected function batchStatus(CandidateImport $candidateImport): array
    {
        $batch = $candidateImport->batch_id === null
            ? null
            : Bus::findBatch($candidateImport->batch_id);

        return [
            'id' => $batch?->id,
            'total_jobs' => $batch === null ? 0 : $batch->totalJobs,
            'pending_jobs' => $batch === null ? 0 : $batch->pendingJobs,
            'failed_jobs' => $batch === null ? 0 : $batch->failedJobs,
            'processed_jobs' => $batch?->processedJobs() ?? 0,
            'progress' => $batch?->progress() ?? 0,
            'finished' => $batch?->finished() ?? false,
            'cancelled' => $batch?->cancelled() ?? false,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function csvHeaders(?string $path): array
    {
        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $stream = fopen($path, 'rb');
        $headers = $stream === false ? [] : (fgetcsv($stream) ?: []);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return array_map(function (?string $header): string {
            $header = strtolower(trim((string) $header));
            $header = ltrim($header, "\xEF\xBB\xBF");

            return $header === 'cv_text' ? 'cv_markdown' : $header;
        }, $headers);
    }

    /**
     * Validate the profile preview before converting the PDF during save.
     */
    protected function cvMarkdown(?string $previewToken, UploadedFile $cvFile): string
    {
        if (blank($previewToken)) {
            return (new CvMarkdownConverter)->convert($cvFile);
        }

        $preview = Cache::get($this->previewCacheKey($previewToken));

        if (! is_array($preview)
            || ! isset($preview['file_hash'])
            || ! is_string($preview['file_hash'])
            || ! hash_equals($preview['file_hash'], $this->fileHash($cvFile))) {
            throw ValidationException::withMessages([
                'cv_file' => 'The PDF preview expired or no longer matches this upload. Please choose the PDF again.',
            ]);
        }

        Cache::forget($this->previewCacheKey($previewToken));

        return (new CvMarkdownConverter)->convert($cvFile);
    }

    protected function previewCacheKey(string $previewToken): string
    {
        return self::CV_PREVIEW_CACHE_PREFIX.$previewToken;
    }

    protected function fileHash(UploadedFile $file): string
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if ($hash === false) {
            throw new \RuntimeException('The uploaded CV could not be read.');
        }

        return $hash;
    }
}
