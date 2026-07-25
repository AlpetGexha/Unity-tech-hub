<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Models\CandidateImport;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportCandidatesFromCsv implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $candidateImportId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = CandidateImport::findOrFail($this->candidateImportId);
        $stream = Storage::disk('local')->readStream($import->file_path);

        if (! is_resource($stream)) {
            throw new \RuntimeException('The candidate CSV could not be opened.');
        }

        $headers = $this->normaliseHeaders(fgetcsv($stream) ?: []);
        $failedRows = $import->failed_rows ?? [];
        $totalRows = 0;
        $validRows = 0;
        $invalidRows = 0;

        while (($row = fgetcsv($stream)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $totalRows++;
            $row = array_pad($row, count($headers), '');
            $data = array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
            $errors = $this->rowErrors($data);

            if ($errors !== []) {
                $invalidRows++;
                $failedRows[] = ['row' => $totalRows + 1, 'errors' => $errors];

                continue;
            }

            $candidate = Candidate::create([
                'job_id' => $import->job_id,
                'name' => trim((string) $data['name']),
                'cv_markdown' => trim((string) ($data['cv_markdown'] ?? $data['cv_text'] ?? '')),
                'github_username' => trim((string) $data['github_username']),
                'linkedin_text' => trim((string) ($data['linkedin_text'] ?? '')) ?: null,
                'evaluation_status' => 'pending',
            ]);

            $validRows++;
            $this->batch()?->add([new EvaluateCandidate($candidate->id)]);
        }

        fclose($stream);

        $import->update([
            'status' => 'running',
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'failed_rows' => $failedRows,
        ]);
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    protected function normaliseHeaders(array $headers): array
    {
        return array_map(function (?string $header): string {
            $header = strtolower(trim((string) $header));
            $header = ltrim($header, "\xEF\xBB\xBF");

            return $header === 'cv_text' ? 'cv_markdown' : $header;
        }, $headers);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn (?string $value): bool => trim((string) $value) === '');
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<int, string>
     */
    protected function rowErrors(array $data): array
    {
        $errors = [];

        foreach (['name' => 'name', 'cv_markdown' => 'CV text', 'github_username' => 'GitHub username'] as $key => $label) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                $errors[] = $label.' is required.';
            }
        }

        return $errors;
    }
}
