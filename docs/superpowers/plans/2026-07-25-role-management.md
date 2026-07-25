# Role Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Turn `/hr/jobs` into a role management area with list, create, read-only view, and edit flows.

**Architecture:** Use resource-style Laravel routes with implicit `JobOpening` binding. Keep validation in a shared form request, keep the controller thin, and use Inertia React pages with a shared role form and generated Wayfinder helpers.

**Tech Stack:** Laravel 13, Inertia v3, React 19, TypeScript, Tailwind CSS v4, Wayfinder, PHPUnit 12.

## Global Constraints

- Preserve existing `JobOpening` relationships and rubric behavior.
- Do not add delete, duplicate, search, filter, pagination, permissions, or dependency changes.
- Keep the existing page/layout and UI component conventions.
- Every backend change must have focused PHPUnit coverage.
- Run Pint for changed PHP files and frontend type, lint, format, and build checks.

---

### Task 1: Add failing feature coverage for role management

**Files:**
- Modify: `tests/Feature/HrJobsTest.php`

**Interfaces:**
- The tests establish the route names and Inertia props expected by the implementation.

- [ ] Add tests for index props, create page, show page with candidates, edit page, update persistence/redirect, route binding 404s, and update validation.
- [ ] Run `php artisan test --compact tests/Feature/HrJobsTest.php` and confirm the new tests fail against the current implementation.

### Task 2: Implement resource-style backend role flow

**Files:**
- Create: `app/Http/Requests/JobOpeningRequest.php`
- Modify: `routes/hr.php`
- Modify: `app/Http/Controllers/JobController.php`

**Interfaces:**
- `JobOpeningRequest::rules(): array` validates `title`, `requirements`, and `priorities`.
- `JobController::index(): Inertia\Response` renders `hr/jobs` with ordered roles.
- `JobController::create(): Inertia\Response` renders `hr/job-form` with no role.
- `JobController::show(JobOpening $job): Inertia\Response` renders `hr/job-show` with the role and candidates.
- `JobController::edit(JobOpening $job): Inertia\Response` renders `hr/job-form` with the role.
- `JobController::store(JobOpeningRequest $request): RedirectResponse` creates and redirects to `hr.jobs.index`.
- `JobController::update(JobOpeningRequest $request, JobOpening $job): RedirectResponse` updates editable fields and redirects to `hr.jobs.index`.

- [ ] Register GET create/show/edit and PUT/PATCH update routes without allowing `{job}` to shadow `create`.
- [ ] Use implicit model binding and eager-load only the candidate fields needed by the detail page.
- [ ] Use the form request for both store and update; redirect successful writes to the roles index.
- [ ] Run the focused PHPUnit file and confirm backend behavior passes.

### Task 3: Regenerate route helpers and build shared React role UI

**Files:**
- Create/modify generated: `resources/js/actions/App/Http/Controllers/JobController.ts`, `resources/js/routes/hr/jobs/index.ts`
- Create: `resources/js/components/role-form.tsx`
- Create: `resources/js/pages/hr/job-form.tsx`
- Create: `resources/js/pages/hr/job-show.tsx`
- Modify: `resources/js/pages/hr/jobs.tsx`

**Interfaces:**
- `RoleForm` accepts optional `job` data and uses the appropriate generated store/update form action.
- `Jobs` receives `jobs: JobSummary[]` and renders View/Edit links plus a Create role link.
- `JobForm` receives optional `job: Job` and renders the shared form for create or edit.
- `JobShow` receives `job: JobDetail` and renders read-only role fields and candidate links/list.

- [ ] Regenerate Wayfinder after route/controller changes and use its helpers instead of hard-coded URLs.
- [ ] Reuse existing Input, Textarea, Label, Button, Spinner, InputError, Head, Link, Card, and layout conventions.
- [ ] Make the list responsive with a horizontally scrollable table and an empty state.
- [ ] Make the detail page read-only, preserve useful whitespace/newlines, and show an empty candidate state when no candidates exist.
- [ ] Run frontend formatting, lint, type checks, and build.

### Task 4: Verify the complete change

**Files:**
- No new files.

- [ ] Run `vendor/bin/pint --dirty --format agent`.
- [ ] Run `php artisan test --compact tests/Feature/HrJobsTest.php`.
- [ ] Run `npm run format:check`, `npm run lint:check`, `npm run types:check`, and `npm run build`.
- [ ] Review the final diff for unintended changes and confirm the existing rubric tests still pass.
