<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobOpeningRequest;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('hr/jobs', [
            'jobs' => JobOpening::query()->orderBy('title')->get(),
        ]);
    }

    /**
     * Show the form for creating a role.
     */
    public function create(): Response
    {
        return Inertia::render('hr/job-form', ['job' => null]);
    }

    /**
     * Show a role and its candidates.
     */
    public function show(JobOpening $job): Response
    {
        $job->load(['candidates' => function (HasMany $query): void {
            $query->select(['id', 'job_id', 'name', 'evaluation_status'])
                ->orderBy('name');
        }]);

        return Inertia::render('hr/job-show', ['job' => $job]);
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(JobOpening $job): Response
    {
        return Inertia::render('hr/job-form', ['job' => $job]);
    }

    /**
     * Save a role and return to the roles list.
     */
    public function store(JobOpeningRequest $request): RedirectResponse
    {
        JobOpening::create($request->validated());

        return to_route('hr.jobs.index');
    }

    /**
     * Update a role and return to the roles list.
     */
    public function update(JobOpeningRequest $request, JobOpening $job): RedirectResponse
    {
        $job->update($request->validated());

        return to_route('hr.jobs.index');
    }
}
