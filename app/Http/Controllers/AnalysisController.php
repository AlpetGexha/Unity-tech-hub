<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
    /**
     * Return a candidate's stored analysis as JSON.
     */
    public function show(Candidate $candidate): JsonResponse
    {
        $analysis = $candidate->analyses()->latest('id')->first();

        abort_if($analysis === null, 404, 'This candidate has not been analysed yet.');

        return response()->json($analysis->result);
    }
}
