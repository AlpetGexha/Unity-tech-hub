<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show every candidate with their role and latest analysis, so HR can see
     * the whole pipeline without knowing a single candidate's id up front.
     */
    public function index(): Response
    {
        $candidates = Candidate::query()
            ->with(['jobOpening:id,title', 'latestAnalysis'])
            ->orderBy('name')
            ->get()
            ->map(function (Candidate $candidate): array {
                $overall = $candidate->latestAnalysis?->result['overall'] ?? null;

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'job_title' => $candidate->jobOpening?->title,
                    'fit_score' => $overall['fit_score'] ?? null,
                    'recommendation' => $overall['recommendation'] ?? null,
                    'analyzed_at' => $candidate->latestAnalysis?->result['generated_at'] ?? null,
                ];
            });

        return Inertia::render('dashboard', ['candidates' => $candidates]);
    }
}
