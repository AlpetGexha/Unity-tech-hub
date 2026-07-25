# HrHire — AI Hiring Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Hackathon note:** Under 3 hours, 3 people, demo not product. Full TDD is applied ONLY to the critical path (analysis JSON contract + analyzer). UI tasks are build-and-eyeball. "Simple and working beats clean and unfinished."

**Goal:** An AI hiring agent that verifies candidate CV claims against real GitHub code (not just scores them) and runs a live voice interview, rendered as a fast, visual dashboard for non-technical judges.

**Architecture:** Laravel 13 + Inertia + React (existing starter kit, kept as-is). SQLite storage. All AI goes through the **Laravel AI SDK** (`laravel/ai`) → OpenAI. Analysis is ONE structured-output agent call (`gpt-4o`, temperature 0). Voice is record→transcribe→grade→speak using the SDK's `Transcription` + `Audio` classes. The `analyses` DB table doubles as the response cache, so seeded candidates load instantly on stage with zero live API calls.

**Tech Stack:** Laravel 13, Inertia v3, React 19 + TypeScript, Vite, Tailwind, `react-chartjs-2` + `chart.js`, SQLite, `laravel/ai` (OpenAI provider).

## Global Constraints

- **AI provider:** OpenAI only, exclusively through `laravel/ai` (**v0.10.1, already in `composer.json` + `vendor/`** — do NOT re-add). No other AI libraries, no raw HTTP to OpenAI.
- **Analysis call:** exactly ONE agent `->prompt()` per candidate. Not a chain. Structured output via `schema()`, with the `#[Strict]` attribute (every field `->required()`, optionals `->nullable()`).
- **Model generates ONLY judgement fields.** `candidate_id`, `job_id`, `generated_at`, `rubric`, and `fairness_note` are **merged in PHP after the call** — never in `schema()`. Strict mode forces the model to emit every schema field, so including metadata there produces hallucinated dates and a reworded fairness note. Keeping them out also cuts tokens and latency on the critical path.
- **Determinism:** `#[Temperature(0)]` on every scored agent (`CandidateAnalyzer`, `InterviewGrader`) so rehearsal reruns give identical numbers.
- **Stage safety net (`DEMO_FAKE_AI=true`):** `CandidateAnalyzer::fake()` auto-generates data conforming to the agent's own schema; `Audio::fake()` and `Transcription::fake()` do the same for voice. If OpenAI is down, rate-limited, or the venue wifi dies, one `.env` flag keeps every screen rendering valid data. Wire this on day one, not at T−10min.
- **Prompt caching:** rubric + job template go FIRST and UNCHANGED in `instructions()`; only candidate data varies in `->prompt()`.
- **Model:** `#[Model('gpt-4o')]` for analysis + grading. STT via `Transcription`, TTS via `Audio` (SDK defaults, OpenAI provider).
- **API key:** `OPENAI_API_KEY` server-side in `.env` only. Never sent to the browser.
- **GitHub:** REST API only via `GitHubClient`. Do NOT clone repos. Disk-cache every response. Use `GITHUB_TOKEN` (raises limit 60→5000/hr).
- **Caching for demo safety:** the `analyses` row keyed by `sha256(job + candidate inputs)` IS the OpenAI cache. GitHub responses disk-cached under `storage/app/cache/github/`.
- **No:** auth, accounts, roles, email, queues, multi-tenant, LinkedIn scraping (pasted text only), repo cloning, build-pipeline additions beyond what the starter already has.
- **Storage:** SQLite (already configured in `.env`).
- **Fairness:** analysis output must carry a `fairness_note`; no protected attributes used. Injected server-side so the wording is guaranteed exact.
- **NO AUTH — and the starter ships auth.** `laravel/fortify` is installed and `routes/web.php:7` has a `Route::middleware(['auth','verified'])` group. **Every HrHire route must be registered OUTSIDE that group.** A route that lands inside it dies on stage at a login screen.
- **The `jobs` table name is TAKEN.** `database/migrations/0001_01_01_000002_create_jobs_table.php` already creates `jobs` for Laravel's database queue driver (`QUEUE_CONNECTION=database`). HrHire's job template table is therefore **`job_openings`**, model **`App\Models\JobOpening`**. Child tables keep the column name `job_id` but must declare the target explicitly: `$table->foreignId('job_id')->constrained('job_openings')`. The JSON contract key stays `job_id` — unchanged.
- **Namespace everything under `hr/`.** `routes/web.php:8` already owns the route name `dashboard`, and `resources/js/pages/dashboard.tsx` already exists — on Windows's case-insensitive filesystem a new `Dashboard.tsx` would collide with it. Use `/hr/...` URLs, `hr.*` route names, and `resources/js/pages/hr/*.tsx`.

## Execution Notes (subagent-driven run, decided 2026-07-25)

- **Branch:** `feat/hrhire`, cut from `main` at `fa6bfbc` (which carries the `laravel/ai` + `laravel/boost` install). Working tree was clean at start.
- **Commits:** stage **explicit paths**, never `git add -A`. Each task commits only the files its own Files block names.
- **Build against fakes; never call the live API.** Every implementer uses `CandidateAnalyzer::fake()`, `InterviewGrader::fake()`, `Audio::fake()`, `Transcription::fake()`. The **only** deliberate real OpenAI calls in the whole build are in Task C3 Step 2, when seeding the 3 demo candidates. This keeps tests deterministic and preserves quota.
- **Two steps are human-only** — a subagent has no microphone. Implementers must NOT claim these done:
  - **C1 Step 4** (end-to-end run with a real mic) → build the path, test it with faked STT/TTS, and leave it for human verification.
  - **C3 Step 4** (record `public/demo/fallback-interview.mp3`) → commit a silent placeholder mp3 so nothing crashes, wire the `Shift+F` handler fully, and leave the actual recording to the human.
- **API verification:** prefer Boost MCP `search-docs` for version-accurate `laravel/ai` APIs. When unavailable, read `vendor/laravel/ai/src/` directly — never guess a signature.

---

## File Structure

**Backend (Task A owns unless noted)**
- `database/migrations/2026_07_25_000001_create_hrhire_tables.php` — `job_openings`, `candidates`, `analyses`, `interviews` tables.
- `app/Models/JobOpening.php` / `Candidate.php` / `Analysis.php` / `Interview.php` — Eloquent models + casts (`analyses.result` cast to `array`).
- `app/Ai/Agents/CandidateAnalyzer.php` — the ONE structured-output agent. `instructions()` = rubric + job template; `schema()` = the §Contract; `#[Model('gpt-4o')] #[Temperature(0)]`.
- `app/Ai/Agents/InterviewGrader.php` — grades one answer transcript vs `ideal_answer_points`; structured output; temp 0.
- `app/Services/GitHubClient.php` — REST: profile, top repos, 2–3 source files. Disk-cached by URL hash.
- `app/Services/CandidateContext.php` — assembles the prompt payload string (CV text + GitHub profile/repos/source snippets).
- `app/Http/Controllers/JobController.php` — job template CRUD (index/store/show).
- `app/Http/Controllers/CandidateController.php` — candidate CRUD + CV upload + `analyze()` (runs analyzer, persists `analyses` row / returns cached).
- `app/Http/Controllers/AnalysisController.php` — returns a candidate's stored analysis JSON (Dashboard data source).
- `app/Http/Controllers/InterviewController.php` — `question()` (next question), `answer()` (STT→grade), `speak()` (TTS mp3), `report()` (decision report payload).
- `routes/hr.php` — ALL HrHire routes, `require`d from `web.php` **outside** the `auth` group. A separate file makes the no-auth guarantee visible at a glance.
- `config/services.php` — add a `github` entry (`'github' => ['token' => env('GITHUB_TOKEN')]`). `GitHubClient` reads `config('services.github.token')`; this key does not exist by default and A3 fails without it.
- `database/seeders/DemoSeeder.php` — 1 job + 3 candidates; calls `analyze()` for each to WARM the cache before demo.
- `database/factories/CandidateFactory.php` — helper for seeding.

**Frontend (Task B owns unless noted)**
- `resources/js/mocks/analysis.json` — mock of the §Contract. Frontend builds against this until Task A's endpoint is live. (Created by A in Task 1 so shapes match.)
- `resources/js/types/analysis.ts` — TypeScript types mirroring the §Contract.
- `resources/js/pages/hr/jobs.tsx` — HR job-template form (title, requirements, "what you care about").
- `resources/js/pages/hr/candidate-form.tsx` — name, **CV paste textarea (primary)**, optional CV file upload, GitHub username, LinkedIn textarea.
- `resources/js/pages/hr/dashboard.tsx` — overall fit + skill charts + claim-verification list. **Note the `hr/` prefix** — a top-level `Dashboard.tsx` collides with the starter's existing `dashboard.tsx` on Windows.
- `resources/js/pages/hr/interview.tsx` — voice interview loop + hidden `Shift+F` fallback (Task C).
- `resources/js/components/SkillChart.tsx` — radar/bar of `skills[]` via `react-chartjs-2`.
- `resources/js/components/ClaimList.tsx` — claim rows colored by `verified`/`weak`/`unsupported` with quoted evidence + `source_ref`.
- `resources/js/components/DecisionReport.tsx` — renders + downloads the decision report (rubric, scores, evidence, fairness note).

**Voice + Data (Task C owns):** `hr/interview.tsx`, `InterviewController.php`, `DemoSeeder.php`, plus `public/demo/fallback-interview.mp3` + a hardcoded fallback transcript for the mic-failure path.

---

## Build Order & Critical Path

1. **T0 (all 3, 10 min):** agree the §Contract below verbatim. A writes `mocks/analysis.json` + `schema()` skeleton; B writes `types/analysis.ts` from it; C reads `interview_questions`/`ideal_answer_points` shape.
2. Then parallel: **A** builds the real analyzer→endpoint; **B** builds Dashboard/forms against the mock; **C** builds the voice loop against the contract.
3. **Integration (~T+1h30):** point `Dashboard.tsx` from `mocks/analysis.json` to the real `AnalysisController` endpoint — identical shape = zero rework. C runs `DemoSeeder` to warm the cache.

**CRITICAL PATH = Task A `CandidateAnalyzer` → Task C `DemoSeeder` warm-cache.** The "instant dashboard on stage" promise depends on seeded `analyses` rows existing. Everything else can slip; this cannot.

## Drop Order If Behind At T−2h (60 min left)

1. **GitHub source-file fetch** → analyze CV + profile + repo names only (claims degrade to `weak`, demo still works).
2. **Live TTS** → show the question as text; keep mic answer + grading.
3. **DecisionReport download** → render on-screen only, skip file export.
4. **Live interview grading** → play scripted `Shift+F` fallback clip as the entire voice segment.

**Never drop:** seeded dashboard loading from cache (Task C3), and the `DEMO_FAKE_AI` switch (Task A5). Those two are what stand between you and a dead stage.

## Time Budget Check

A = 20+40+20+35+15 = **2h10** · B = 45+50+15 = **1h50** · C = 50+20+40 = **1h50**. Against a 3h wall with 10min at T0, A is the tightest lane and A is also the critical path. **If A slips past T+1h20 on the analyzer, hand A3 (`GitHubClient`) to C or drop it** — it is already #1 in the drop order, and A2→A4 must not wait on it.

---

## THE JSON CONTRACT (define first — everything builds against this)

`analyses.result` stores exactly this object, and the frontend consumes exactly this object. But it is assembled from **two** sources:

- **MODEL-GENERATED (goes in `schema()`):** `overall`, `skills`, `claims`, `interview_questions`. Only these four.
- **SERVER-MERGED (never in `schema()`):** `candidate_id`, `job_id`, `generated_at`, `rubric`, `fairness_note`. PHP adds these to the response array before saving.

```php
// CandidateController@analyze — how the two halves join
$result = (new CandidateAnalyzer($job))->prompt($context)->toArray();  // 4 model keys
$result += [
    'candidate_id'  => (string) $candidate->id,
    'job_id'        => (string) $job->id,
    'generated_at'  => now()->toIso8601String(),
    'rubric'        => $job->rubric(),            // our input, echoed back — not the model's
    'fairness_note' => Analysis::FAIRNESS_NOTE,   // exact constant wording
];
```

```jsonc
{
  "candidate_id": "string",              // SERVER
  "job_id": "string",                    // SERVER
  "generated_at": "string",              // SERVER, ISO-8601
  "overall": {                           // MODEL
    "fit_score": 0,                       // 0-100
    "recommendation": "strong_yes | yes | maybe | no",
    "summary": "string"                   // 2-3 sentences
  },
  "skills": [                            // MODEL
    { "name": "string", "score": 0, "weight": 0, "reasoning": "string" }  // score/weight 0-100
  ],
  "claims": [                            // MODEL
    {
      "claim": "string",
      "status": "verified | weak | unsupported",
      "evidence": "string",               // exact quote from CV/GitHub/source
      "source": "cv | github_profile | repo | source_file",
      "source_ref": "string"              // "user/repo/path:42" or ""
    }
  ],
  "interview_questions": [               // MODEL
    {
      "question": "string",
      "targets_skill": "string",
      "grounded_in": "string",            // "user/repo/path:Lstart-Lend" or "job_requirement"
      "ideal_answer_points": ["string"]
    }
  ],
  "rubric": {                            // SERVER — echoed from the Job record
    "criteria": [ { "name": "string", "weight": 0, "description": "string" } ],
    "scale": "string"
  },
  "fairness_note": "No protected attributes (age, gender, ethnicity, nationality, religion) were used in this assessment."  // SERVER constant
}
```

**Verified schema builder support** (`Illuminate\JsonSchema`, read from `vendor/`): `$schema->object([...])` nests, `$schema->array()->items($schema->object([...]))` gives arrays of objects, and `->enum([...])`, `->required()`, `->nullable()`, `->min()/->max()` exist on the relevant types. The contract above is fully expressible — confirmed, not assumed.

---

## Task 1 (A1): Migrations + Models (Owner: Backend) — 20 min

**Files:**
- Create: `database/migrations/2026_07_25_000001_create_hrhire_tables.php`
- Create: `app/Models/{JobOpening,Candidate,Analysis,Interview}.php`
- Create: `resources/js/mocks/analysis.json` (fill with one realistic example of the §Contract)

**Interfaces:**
- Produces: `Analysis` model with `$casts = ['result' => 'array']`; `analyses` table columns `candidate_id`, `job_id`, `input_hash` (unique), `result` (json), `timestamps`.

- [ ] **Step 1:** Write migration. `job_openings`(id, title, requirements text, priorities text, timestamps). `candidates`(id, job_id, name, **`cv_text` (text, the PRIMARY CV input — pasted)**, `cv_path` (string, **nullable** — optional file upload only), github_username, linkedin_text nullable, timestamps). `analyses`(id, candidate_id, job_id, input_hash unique, result json, timestamps). `interviews`(id, candidate_id, result json, timestamps).
- [ ] **Step 2:** Run `php artisan migrate`. Expected: 4 tables created in SQLite.
- [ ] **Step 3:** Write the 4 models with `$fillable` and `Analysis::$casts['result'] => 'array'`. `JobOpening` needs `protected $table = 'job_openings'` only if you don't name it conventionally — `JobOpening` already maps to `job_openings`, so no override is needed. Also create `database/factories/JobOpeningFactory.php` and `database/factories/CandidateFactory.php` — **Task 2's test calls `JobOpening::factory()` and will fail without it.**
- [ ] **Step 4:** Write `mocks/analysis.json` — one full example matching the §Contract (3 skills, 4 claims mixed statuses, 3 questions).
- [ ] **Step 5:** `git add database/migrations app/Models resources/js/mocks && git commit -m "feat: hrhire schema, models, analysis mock"`

## Task 2 (A2): Install Laravel AI SDK + Analyzer agent (Owner: Backend) — 40 min · CRITICAL PATH

**Files:**
- Create: `app/Ai/Agents/CandidateAnalyzer.php`
- Create: `app/Services/CandidateContext.php`
- Test: `tests/Feature/CandidateAnalyzerTest.php`

**Interfaces:**
- Consumes: `JobOpening` model, assembled context string from `CandidateContext::build(Candidate)`.
- Produces: `(new CandidateAnalyzer($job))->prompt($context, attachments: [...])` → array-accessible `StructuredAgentResponse` matching the §Contract.

- [ ] **Step 1:** `laravel/ai` v0.10.1 is **already installed** — do not re-require it. Just run `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` and `php artisan migrate` (the SDK ships `create_agent_conversations_table`). Add `OPENAI_API_KEY`, `GITHUB_TOKEN`, and `DEMO_FAKE_AI=false` to `.env`.
- [ ] **Step 2 (TDD — contract shape):** Write `CandidateAnalyzerTest`. **The fake is a static method on your own agent class** (`CandidateAnalyzer::fake()` via the `Promptable` trait) — there is no `Agent::fake([...])` facade. Called with no arguments on a `HasStructuredOutput` agent, it auto-generates a response matching your `schema()`, which is exactly what we want to assert against.

```php
public function test_analyzer_result_matches_contract(): void {
    CandidateAnalyzer::fake();                      // auto-generates schema-conforming data
    $job = JobOpening::factory()->create();
    $r = (new CandidateAnalyzer($job))->prompt('ctx');

    // the four MODEL-generated keys must be present and well-typed
    $this->assertIsInt($r['overall']['fit_score']);
    $this->assertContains($r['claims'][0]['status'], ['verified','weak','unsupported']);
    $this->assertNotEmpty($r['skills']);
    $this->assertNotEmpty($r['interview_questions'][0]['ideal_answer_points']);

    // metadata must NOT come from the model
    $this->assertArrayNotHasKey('generated_at', $r->toArray());
    $this->assertArrayNotHasKey('fairness_note', $r->toArray());

    CandidateAnalyzer::assertPrompted(fn ($p) => $p->contains('ctx'));
}
```

- [ ] **Step 3:** Run `php artisan test --filter=CandidateAnalyzerTest`. Expected: FAIL (class missing).
- [ ] **Step 4:** Write `CandidateAnalyzer`: `implements Agent, HasStructuredOutput; use Promptable;` with `#[Provider(Lab::OpenAI)] #[Model('gpt-4o')] #[Temperature(0)] #[Strict]` (all four attributes exist in `Laravel\Ai\Attributes`; `Lab::OpenAI` is in `Laravel\Ai\Enums\Lab`). `instructions()` returns rubric + `$this->job->requirements`/`priorities` FIRST and unchanged, plus a fallback default prompt when the job template is empty. `schema(JsonSchema $s)` expresses **only the four MODEL keys** — `overall`, `skills`, `claims`, `interview_questions` — every field `->required()`, arrays via `->array()->items($s->object([...]))`, enums via `->string()->enum([...])`.
- [ ] **Step 5:** Write `CandidateContext::build(Candidate $c): string` — concatenates CV text (or note "see attachment"), GitHub profile JSON, top-repo list, and fetched source snippets (from `GitHubClient`).
- [ ] **Step 6:** Run `php artisan test --filter=CandidateAnalyzerTest`. Expected: PASS.
- [ ] **Step 7:** `git commit -m "feat: Laravel AI SDK + CandidateAnalyzer structured agent"`

## Task 3 (A3): GitHubClient (disk-cached) (Owner: Backend) — 20 min

**Files:**
- Create: `app/Services/GitHubClient.php`
- Test: `tests/Feature/GitHubClientTest.php`

**Interfaces:**
- Produces: `GitHubClient::profile(string $user): array`, `::topRepos(string $user, int $n=3): array`, `::sourceFiles(string $user, string $repo, int $n=2): array` (each `['path'=>, 'content'=>]`). Every call disk-cached under `storage/app/cache/github/{sha1(url)}.json`.

- [ ] **Step 1 (TDD):** Write test with `Http::fake()` for the profile endpoint; call twice; assert only ONE HTTP call fires (second served from disk cache).
- [ ] **Step 2:** Run test. Expected: FAIL.
- [ ] **Step 3:** Implement with `Http::withToken(config('services.github.token'))` + a `remember` helper that reads/writes `storage/app/cache/github/`.
- [ ] **Step 4:** Run test. Expected: PASS.
- [ ] **Step 5:** `git commit -m "feat: disk-cached GitHubClient"`

## Task 4 (A4): Controllers + routes + analyze()/cache (Owner: Backend) — 35 min · CRITICAL PATH

**Files:**
- Create: `app/Http/Controllers/{Job,Candidate,Analysis}Controller.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `POST /candidates/{c}/analyze` → computes `input_hash = sha256(job + cv + github + linkedin)`; if `analyses` row exists returns it, else runs `CandidateAnalyzer`, stamps `generated_at`, saves row, returns `result`. `GET /candidates/{c}/analysis` → stored `result` JSON for the Dashboard.

- [ ] **Step 1:** `JobController@store/index/show` (Inertia). `CandidateController@create` must pass a **`jobs` Inertia prop** — `{id, title}[]` from `JobOpening::all()` — because the candidate form has a required role `<select>`. `CandidateController@store` saves `cv_text` from the textarea (optional file upload to `storage/app/cv/`) and **must validate `job_id` as `required|exists:job_openings,id`** — `candidates.job_id` is NOT NULL, so an unvalidated store fails with a raw constraint violation. On success, **redirect back with prop `candidate: {id, name}`** (the frontend's Analyze button renders off this). `POST /hr/candidates/{id}/analyze` redirects to `/hr/candidates/{id}`.
- [ ] **Step 1b — add `JobOpening::rubric()`.** The §Contract server-merge calls `$job->rubric()` and **the method does not exist yet**. Return the array shape `['criteria' => [{name, weight, description}], 'scale' => string]`, derived from the job's `priorities`/`requirements`. Without it, Step 2 fatals.
- [ ] **Step 2:** `CandidateController@analyze` with the hash-cache logic above, **including the server-merge block** from §Contract (`generated_at`, `rubric`, `fairness_note`, ids). `AnalysisController@show` returns `result`.
- [ ] **Step 2b — WIRE `GitHubClient` INTO `CandidateContext`. Do not skip; this fails silently.** `CandidateContext::build(Candidate $c, array $github = [])` takes the GitHub payload as its second argument (Task 2 could not call `GitHubClient` because it did not exist yet). **If you call `build($candidate)` with one argument, everything type-checks, every test passes, and the analysis still returns — but every prompt says "could not be fetched", and all claims silently degrade to `weak`. It will look like a bad analyzer, not a wiring bug.** Required:

```php
$github = [
    'profile' => $gitHubClient->profile($candidate->github_username),
    'repos'   => $gitHubClient->topRepos($candidate->github_username),
    'sources' => $gitHubClient->sourceFiles($candidate->github_username, $repo),
];
$context = CandidateContext::build($candidate, $github);
```

Keep the `= []` default — Task 11's seeder may legitimately want the GitHub-less path. Add a test asserting the context passed to the analyzer actually contains GitHub data when the client returns some; that is the only thing that catches this regression.
- [ ] **Step 3:** Create `routes/hr.php` with pages `/hr/jobs`, `/hr/candidates/create`, `/hr/candidates/{c}`, `/hr/candidates/{c}/interview` (route names `hr.*`) + the JSON endpoints. Then add `require __DIR__.'/hr.php';` to `routes/web.php` **outside and below** the `Route::middleware(['auth','verified'])` group.
- [ ] **Step 4 (auth guard check — demo blocker):** Run `php artisan route:list --path=hr`. Expected: **every** `hr.*` row shows NO `auth` or `verified` middleware. Then open `/hr/jobs` in a private/incognito window and confirm it renders instead of redirecting to login.
- [ ] **Step 5:** Manual check: `php artisan tinker` → create job+candidate → hit analyze → confirm a row is written and a second call returns instantly (no API hit).
- [ ] **Step 6:** `git commit -m "feat: job/candidate/analysis controllers, public hr routes, cache-keyed analyze"`

## Task 5 (A5): Demo safety — `DEMO_FAKE_AI` kill switch (Owner: Backend) — 15 min · DEMO INSURANCE

**Files:** Create `app/Providers/DemoAiServiceProvider.php` (or a boot hook in `AppServiceProvider`)

**Why:** venue wifi, an expired key, or an OpenAI incident at 4pm otherwise ends the demo. The SDK auto-generates schema-conforming fakes, so full coverage costs ~15 minutes.

- [ ] **Step 1:** In `AppServiceProvider::boot()`, when `config('app.demo_fake_ai')` is true, call `CandidateAnalyzer::fake()`, `InterviewGrader::fake()`, `Audio::fake()`, and `Transcription::fake()`. Add `'demo_fake_ai' => env('DEMO_FAKE_AI', false)` to `config/app.php`.
- [ ] **Step 2:** Set `DEMO_FAKE_AI=true`, run the full demo path (job → candidate → analyze → dashboard → interview). Expected: every screen renders with valid data and zero network calls.
- [ ] **Step 3:** Set it back to `false`, confirm real analysis still works. Commit `feat: DEMO_FAKE_AI offline kill switch`.
- [ ] **Step 4:** Write the flag and its exact `.env` line on paper / a sticky note for whoever drives the demo.

## Task 6 (B1): Types + Dashboard against mock (Owner: Frontend) — 45 min

**Files:**
- Create: `resources/js/types/analysis.ts`, `resources/js/components/{SkillChart,ClaimList}.tsx`, `resources/js/pages/hr/dashboard.tsx`

**Interfaces:**
- Consumes: `mocks/analysis.json` (dev) then `GET /candidates/{c}/analysis` (prod), both = §Contract.

- [ ] **Step 1:** `npm i chart.js react-chartjs-2`. Write `types/analysis.ts` mirroring the §Contract.
- [ ] **Step 2:** `SkillChart.tsx` — radar (or bar) of `skills[]` (label=name, value=score) via `react-chartjs-2`.
- [ ] **Step 3:** `ClaimList.tsx` — one row per claim; badge color by status (green/amber/red); show `evidence` as a quote + `source_ref` as monospace.
- [ ] **Step 4:** `hr/dashboard.tsx` — header with `overall.fit_score` (big number) + `recommendation` pill + `summary`; then `<SkillChart>` + `<ClaimList>`. Import `mocks/analysis.json` for now. **Do not name this file `Dashboard.tsx` at the top level** — it collides with the starter's existing `dashboard.tsx`.
- [ ] **Step 5:** `npm run dev`, eyeball at `/hr/candidates/1`. Commit `feat: dashboard, skill chart, claim list (mock-backed)`.

## Task 7 (B2): Forms + DecisionReport (Owner: Frontend) — 50 min

**Files:**
- Create: `resources/js/pages/hr/{jobs,candidate-form}.tsx`, `resources/js/components/DecisionReport.tsx`

- [ ] **Step 1:** `hr/jobs.tsx` — Inertia form: title, requirements (textarea), priorities (textarea) → `POST /hr/jobs`.
- [ ] **Step 2:** `hr/candidate-form.tsx` — name, **CV paste `<textarea>` as the primary input** (file upload optional and secondary — pasted text removes any dependency on the model accepting PDFs, which is unverified), GitHub username, LinkedIn `<textarea>` → `POST /hr/candidates`. On success, an "Analyze" button → `POST /hr/candidates/{c}/analyze` then redirect to dashboard.
- [ ] **Step 3:** `DecisionReport.tsx` — renders `rubric.criteria`, all `skills` scores, `claims` evidence, and `fairness_note`; a "Download" button serializes the report to a `.txt`/`.json` Blob and triggers download.
- [ ] **Step 4:** Eyeball each page. Commit `feat: job/candidate forms + downloadable decision report`.

## Task 8 (B3): Integrate Dashboard to real endpoint (Owner: Frontend, after A4) — 15 min

- [ ] **Step 1:** Swap `hr/dashboard.tsx` data source from `mocks/analysis.json` to the Inertia prop / `GET /hr/candidates/{c}/analysis`. Shapes identical → no component changes.
- [ ] **Step 2:** Verify seeded candidate renders. Commit `feat: dashboard wired to live analysis endpoint`.

## Task 9 (C2): InterviewGrader agent (Owner: Voice+Data) — 20 min

**Files:** Create `app/Ai/Agents/InterviewGrader.php`

- [ ] **Step 1:** `implements Agent, HasStructuredOutput; use Promptable;` with `#[Provider(Lab::OpenAI)] #[Model('gpt-4o')] #[Temperature(0)] #[Strict]`. `instructions()` = "score the answer 0-100 against these ideal points" + the question's `ideal_answer_points`. `schema()` = `['score' => $s->integer()->min(0)->max(100)->required(), 'notes' => $s->string()->required()]`.
- [ ] **Step 2:** Manual check via tinker with a canned transcript. Commit `feat: InterviewGrader agent`.

## Task 10 (C1): Voice interview loop (Owner: Voice+Data) — 50 min

**Files:**
- Create/own: `resources/js/pages/hr/interview.tsx`, `app/Http/Controllers/InterviewController.php`

**Interfaces:**
- Consumes: `interview_questions[]` from the analysis. Produces: `GET /hr/candidates/{c}/interview/question?i=N`, `POST /hr/candidates/{c}/interview/answer` (multipart audio → STT→grade→JSON), `POST /hr/interview/speak` (text→mp3 url), `POST /hr/candidates/{c}/interview/report`.

- [ ] **Step 1:** `InterviewController@speak`: `Audio::of($text)->generate()->storePublicly()` → return the public URL (`storePublicly` is the right call here; a plain `store()` lands on a non-public path the `<audio>` tag cannot reach). `@question`: return `interview_questions[i]`.
- [ ] **Step 2:** `InterviewController@answer`: `$t = Transcription::fromUpload($request->file('audio'))->generate();` then `(new InterviewGrader($question))->prompt((string) $t)` → return `{transcript, score, notes}`.
- [ ] **Step 3:** `hr/interview.tsx`: render current question, play its TTS `<audio>`, `MediaRecorder` to capture the answer, POST it, show the grade, advance. Loop over all questions. **Serve over `localhost` or https** — `getUserMedia` is blocked on insecure origins, so a demo over a LAN IP will silently fail to get a mic.
- [ ] **Step 4:** Manual run one question end-to-end with a real mic. Commit `feat: record→transcribe→grade→speak interview loop`.

## Task 11 (C3): DemoSeeder warm-cache + fallback (Owner: Voice+Data) — 40 min · CRITICAL PATH

**Files:** Create `database/seeders/DemoSeeder.php`, `database/factories/CandidateFactory.php`, add `public/demo/fallback-interview.mp3`

- [ ] **Step 1:** `DemoSeeder`: create 1 job + 3 realistic candidates (real public GitHub usernames so claims verify against real code).
- [ ] **Step 2:** In the seeder, call `CandidateController@analyze` (or the analyzer service) for each candidate so `analyses` rows are written → cache warmed.
- [ ] **Step 3:** Run `php artisan migrate:fresh --seed`. Confirm 3 `analyses` rows exist. Then prove no live call happens on load: add `CandidateAnalyzer::preventStrayPrompts()` in the test/demo boot path and open each dashboard — a cached read must not throw. (Stronger and faster than unplugging the network.)
- [ ] **Step 4:** Record one clean question+answer exchange to `public/demo/fallback-interview.mp3`; hardcode its transcript. Add a `Shift+F` key handler in `hr/interview.tsx` that plays the clip + shows the scripted grade if the mic fails.
- [ ] **Step 5:** Commit `feat: demo seeder warm-cache + mic-failure fallback`.

---

## Self-Review

**Review pass 2 (against installed `laravel/ai` v0.10.1 source, not from memory):** Fixed 3 blockers — the non-existent `Agent::fake([...])` API (real one is `CandidateAnalyzer::fake()` via `Promptable`); the `dashboard` route-name and `dashboard.tsx` filename collision with the starter kit; and the Fortify `auth` middleware group that would have put the demo behind a login. Added 4 improvements — the `DEMO_FAKE_AI` kill switch (A5), the model-generated vs server-merged contract split (kills hallucinated `generated_at` and reworded fairness notes, saves tokens), the explicit `#[Strict]` attribute, and `config/services.php` github key. Confirmed the schema builder supports the nested arrays-of-objects our contract needs.

**Spec coverage:** HR job template + empty-fallback → A2 `instructions()`. Candidate record (name/CV/GitHub/LinkedIn) → A1 schema + B2 form. CV+GitHub+source analysis, one strict call, temp 0, rubric-first caching → A2. Dashboard (fit/charts/claims) → B1. Voice interview grounded in job + code → C1/C2 (`interview_questions.grounded_in`). Decision report + fairness note + download → B2 + `fairness_note`. Disk cache OpenAI/GitHub → analyses table (A4) + GitHubClient (A3). Seed 3, warm cache, instant load → C3. Mic fallback → C3. Realtime evaluated & rejected → recorded in Architecture. All covered.

**Placeholders:** none — every step names files, methods, and commands.

**Type consistency:** `result`/§Contract keys used identically in A2 schema, B1 types, and B3 integration. `interview_questions`/`ideal_answer_points` consistent across A2 and C2.
