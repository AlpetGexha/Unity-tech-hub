# Batch Candidate Evaluation Design

## Goal

Allow an HR user to upload one CSV containing many candidates for one selected role. The request should return quickly while candidate creation and AI evaluation continue in the queue. Each candidate must be independently retryable and the user must be able to see batch progress.

The existing single-candidate flow remains available and keeps its current synchronous behavior. The batch path reuses the same evaluation logic so the two paths produce the same analysis contract and cache behavior.

## CSV contract

The upload selects one existing job opening. The CSV has a header row with:

- `name` — required, non-empty candidate name.
- `cv_text` — required, non-empty pasted CV text.
- `github_username` — required, non-empty public GitHub username.
- `linkedin_text` — optional pasted LinkedIn text.

The upload does not accept file paths from CSV rows. Candidate CV files remain a separate manual-upload concern. Unknown columns are ignored, while missing required headers reject the upload before it is queued.

Rows are validated independently. A malformed row is recorded as a failed import row and does not prevent valid rows from being created and evaluated. Empty rows are ignored.

## Architecture

### Candidate import record

Add a `candidate_imports` table and model containing the selected `job_id`, stored CSV path, generated queue `batch_id`, original filename, and lifecycle counters/status. The record is the stable application-level reference used by the UI; Laravel's `job_batches` table remains the queue system's source for batch progress and failed jobs.

The batch migration is created with Laravel's queue-batch migration command when absent. The import migration references the job opening and stores enough information to inspect an import after the upload request is complete.

### Upload request

Add an import endpoint and form section to the candidate page:

1. Validate `job_id` and a CSV file with a bounded size.
2. Store the file on the private/local disk under an import-specific path.
3. Create a pending `CandidateImport` record.
4. Dispatch a named Laravel batch containing `ImportCandidatesFromCsv`.
5. Save the returned batch UUID on the import record.
6. Redirect to the import status page.

The request does not parse the entire file or call the AI provider.

### Import job

`ImportCandidatesFromCsv` is a batchable queue job. It opens the stored CSV as a stream, validates the header contract, creates candidates associated with the selected job, and adds one `EvaluateCandidate` job to the same batch for each valid row. It updates import counters as rows are accepted or rejected.

The job checks whether its batch has been cancelled before doing work. It uses a bounded retry policy appropriate for file and database failures. The original file is retained until the batch finishes, then removed in the batch `finally` callback.

### Evaluation job

`EvaluateCandidate` is a batchable queue job with the candidate ID. It checks cancellation, loads the candidate and role, and delegates to a shared candidate evaluation service extracted from the current controller. The service:

- computes the existing input hash;
- reuses an existing analysis for that hash;
- gathers the same GitHub evidence;
- calls `CandidateAnalyzer` only when the hash is not already stored;
- merges server-owned metadata (`candidate_id`, `job_id`, `generated_at`, rubric, and fairness note);
- creates the analysis row.

The unique analysis hash protects against duplicate work when a job is retried or the same candidate is imported again. Provider failures are allowed to retry according to the job policy; permanent failures are represented by Laravel's failed batch job information and the import status.

### Batch lifecycle and status

Batch callbacks update the import record:

- `before`: mark it running;
- `progress`: refresh processed/failed counters;
- `then`: mark completed when all jobs succeed;
- `catch`: mark failed when a job failure is detected;
- `finally`: mark finished, persist final counts, and delete the private CSV.

Expose a status endpoint that returns the import record plus the corresponding Laravel batch fields: total jobs, pending jobs, failed jobs, processed jobs, percentage, finished, cancelled, and failed-row messages. The status page uses Inertia polling with a non-overlapping (`rest`) mode and stops once the batch is finished.

The progress percentage is the Laravel batch percentage. The import UI also distinguishes candidate rows rejected during parsing from queue jobs that failed during evaluation.

## Routes and UI

Add routes under the existing `hr.` route group:

- `POST /hr/candidate-imports` — upload and dispatch an import.
- `GET /hr/candidate-imports/{candidateImport}` — render the progress page.
- `GET /hr/candidate-imports/{candidateImport}/status` — return current progress for polling.

Extend `resources/js/pages/hr/candidate-form.tsx` with a separate batch-upload section. The role select is shared with the existing candidate form. The section explains the required headers and links to the progress page after submission.

Add `resources/js/pages/hr/candidate-import.tsx` with total, completed, failed, and percentage indicators, row-validation errors, and links to completed candidate dashboards when available. It must show a useful pending/loading state before the first poll response and stop polling after completion.

No new authentication behavior is introduced; the existing HR route convention remains unchanged.

## Error handling and safety

- Reject non-CSV or oversized uploads during validation.
- Reject missing required headers before creating candidate records.
- Keep row errors isolated so one bad row does not cancel valid evaluations.
- Treat blank required row values as row errors.
- Never trust CSV-provided file paths or arbitrary storage locations.
- Use private storage for the uploaded CSV and delete it after the batch finalizes.
- Do not include candidate PII in queue names, logs, or status URLs beyond the existing candidate ID routes.
- Preserve the existing fairness metadata and analysis cache semantics.

## Tests

Add PHPUnit feature tests covering:

1. Valid CSV upload stores the file, creates the import, and dispatches one named batch.
2. Missing headers and invalid file types are rejected without dispatching a batch.
3. The importer creates valid candidates under the selected role and records invalid-row messages without stopping.
4. The evaluation job calls the shared service and stores an analysis with server-owned metadata; a repeated job reuses the input-hash cache.
5. Batch callbacks expose accurate status and completed/failed lifecycle state.
6. The status endpoint returns batch progress and the import page renders the expected props.
7. Existing single-candidate analysis tests continue to pass.

Use `Bus::fake()` for dispatch assertions, `CandidateAnalyzer::fake()` and existing HTTP fakes for execution tests, and the queue-batch fake/interaction facilities where appropriate. Run the narrowest affected PHPUnit tests, then Pint for changed PHP files.

## Scope boundaries

This feature does not add per-row role selection, background CV parsing, spreadsheet-format support, automatic duplicate candidate merging, cancellation controls, or a new worker infrastructure. A production deployment still needs a queue worker running the configured queue connection.
