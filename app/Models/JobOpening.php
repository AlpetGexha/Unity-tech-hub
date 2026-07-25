<?php

namespace App\Models;

use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $requirements
 * @property string $priorities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'requirements', 'priorities'])]
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
    use HasFactory;

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}
