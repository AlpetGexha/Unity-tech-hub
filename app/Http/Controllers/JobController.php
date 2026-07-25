<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Show the form for describing a role.
     */
    public function index(): Response
    {
        return Inertia::render('hr/jobs');
    }

    /**
     * Save a role and send the user on to add the first candidate for it.
     */
    public function store(Request $request): RedirectResponse
    {
        JobOpening::create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'requirements' => ['required', 'string'],
            'priorities' => ['required', 'string'],
        ]));

        return to_route('hr.candidates.create');
    }
}
