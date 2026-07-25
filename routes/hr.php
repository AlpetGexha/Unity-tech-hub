<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HrHire routes
|--------------------------------------------------------------------------
|
| Every HrHire route lives here, and this file is required from web.php
| OUTSIDE the Fortify ['auth', 'verified'] group. There is no login in this
| product, so a route that drifted inside that group would meet a demo
| audience with a login screen. Keeping them in one file makes that
| guarantee visible at a glance, and HrRoutesAreNotBehindAuthTest enforces it.
|
*/

Route::prefix('hr')->name('hr.')->group(function (): void {
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');

    Route::get('candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('candidates/create', [CandidateController::class, 'create'])->name('candidates.create');
    Route::post('candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::get('candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
    Route::post('candidates/{candidate}/analyze', [CandidateController::class, 'analyze'])->name('candidates.analyze');

    Route::get('candidates/{candidate}/analysis', [AnalysisController::class, 'show'])->name('candidates.analysis');

    Route::get('candidates/{candidate}/interview', [InterviewController::class, 'index'])->name('candidates.interview');
    Route::post('interview/speak', [InterviewController::class, 'speak'])->name('interview.speak');
    Route::get('candidates/{candidate}/interview/question', [InterviewController::class, 'question'])->name('candidates.interview.question');
    Route::post('candidates/{candidate}/interview/answer', [InterviewController::class, 'answer'])->name('candidates.interview.answer');
    Route::post('candidates/{candidate}/interview/report', [InterviewController::class, 'report'])->name('candidates.interview.report');
});
