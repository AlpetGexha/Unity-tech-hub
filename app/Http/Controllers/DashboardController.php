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
        return Inertia::render('dashboard', ['candidates' => Candidate::evaluationOverview()]);
    }
}
