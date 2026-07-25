<?php

namespace App\Models;

use Database\Factories\CandidateImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property string|null $batch_id
 * @property string $file_path
 * @property string $original_filename
 * @property string $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property array<int, array{row: int, errors: array<int, string>}> $failed_rows
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'job_id',
    'batch_id',
    'file_path',
    'original_filename',
    'status',
    'total_rows',
    'valid_rows',
    'invalid_rows',
    'failed_rows',
])]
class CandidateImport extends Model
{
    /** @use HasFactory<CandidateImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_rows' => 'array',
        ];
    }

    /**
     * @return BelongsTo<JobOpening, $this>
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_id');
    }
}
