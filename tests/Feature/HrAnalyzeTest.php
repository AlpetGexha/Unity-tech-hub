<?php

namespace Tests\Feature;

use App\Ai\Agents\CandidateAnalyzer;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\GitHubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class HrAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(GitHubClient::cachePath());

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(GitHubClient::cachePath());

        parent::tearDown();
    }

    public function test_an_analysis_is_generated_stored_and_shown(): void
    {
        $this->fakeGitHub();
        CandidateAnalyzer::fake(fn (): array => $this->modelResult());

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze')
            ->assertRedirect('/hr/candidates/'.$candidate->id);

        $this->assertDatabaseCount('analyses', 1);

        $result = Analysis::firstOrFail()->result;

        $this->assertSame(78, $result['overall']['fit_score']);
        $this->assertSame('Laravel', $result['skills'][0]['name']);
        $this->assertNotEmpty($result['claims']);
        $this->assertNotEmpty($result['interview_questions']);
    }

    public function test_the_metadata_is_merged_in_php_and_wins_over_anything_the_model_sends(): void
    {
        $this->fakeGitHub();
        CandidateAnalyzer::fake(fn (): array => $this->modelResult() + [
            'fairness_note' => 'A reworded note the model made up.',
            'generated_at' => '1999-01-01T00:00:00+00:00',
        ]);

        $candidate = $this->candidate();
        $job = $candidate->jobOpening;

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        $result = Analysis::firstOrFail()->result;

        $this->assertSame((string) $candidate->id, $result['candidate_id']);
        $this->assertSame((string) $job->id, $result['job_id']);
        $this->assertSame(Analysis::FAIRNESS_NOTE, $result['fairness_note']);
        $this->assertSame($job->rubric(), $result['rubric']);
        $this->assertNotSame('1999-01-01T00:00:00+00:00', $result['generated_at']);
        $this->assertTrue(Carbon::hasFormat($result['generated_at'], Carbon::ATOM));
    }

    public function test_re_analysing_unchanged_inputs_costs_no_api_call(): void
    {
        $this->fakeGitHub();

        $prompts = 0;
        CandidateAnalyzer::fake(function () use (&$prompts): array {
            $prompts++;

            return $this->modelResult();
        });

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');
        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        $this->assertSame(1, $prompts);
        $this->assertDatabaseCount('analyses', 1);
    }

    public function test_editing_the_cv_invalidates_the_cached_analysis(): void
    {
        $this->fakeGitHub();

        $prompts = 0;
        CandidateAnalyzer::fake(function () use (&$prompts): array {
            $prompts++;

            return $this->modelResult();
        });

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        $candidate->update(['cv_text' => 'A completely rewritten CV.']);

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        $this->assertSame(2, $prompts);
        $this->assertDatabaseCount('analyses', 2);
    }

    public function test_the_prompt_carries_the_github_evidence_the_client_fetched(): void
    {
        $this->fakeGitHub();
        CandidateAnalyzer::fake(fn (): array => $this->modelResult());

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        CandidateAnalyzer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('"login": "jamierivera"')
            && $prompt->contains('ledger')
            && $prompt->contains('jamierivera/ledger/src/Posting.php')
            && $prompt->contains('final class Posting {}'));
    }

    public function test_an_analysis_still_runs_when_github_is_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
        CandidateAnalyzer::fake(fn (): array => $this->modelResult());

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze')
            ->assertRedirect('/hr/candidates/'.$candidate->id);

        $this->assertDatabaseCount('analyses', 1);
    }

    public function test_the_stored_analysis_is_available_as_json(): void
    {
        $this->fakeGitHub();
        CandidateAnalyzer::fake(fn (): array => $this->modelResult());

        $candidate = $this->candidate();

        $this->post('/hr/candidates/'.$candidate->id.'/analyze');

        $this->get('/hr/candidates/'.$candidate->id.'/analysis')
            ->assertOk()
            ->assertJsonPath('overall.fit_score', 78)
            ->assertJsonPath('fairness_note', Analysis::FAIRNESS_NOTE);
    }

    public function test_the_json_endpoint_is_a_404_before_anything_has_been_analysed(): void
    {
        $this->get('/hr/candidates/'.$this->candidate()->id.'/analysis')->assertNotFound();
    }

    /**
     * Create a candidate whose GitHub username matches the faked endpoints.
     */
    protected function candidate(): Candidate
    {
        return Candidate::factory()->create([
            'job_id' => JobOpening::factory()->create([
                'title' => 'Senior Laravel Engineer',
                'requirements' => 'Five years of PHP.',
                'priorities' => 'Verifiable delivery over claims.',
            ]),
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'github_username' => 'jamierivera',
            'linkedin_text' => 'Open to backend roles.',
        ]);
    }

    /**
     * The four model-generated keys of the contract, fixed so assertions are exact.
     *
     * @return array<string, mixed>
     */
    protected function modelResult(): array
    {
        return [
            'overall' => ['fit_score' => 78, 'recommendation' => 'yes', 'summary' => 'Strong evidence.'],
            'skills' => [['name' => 'Laravel', 'score' => 84, 'weight' => 40, 'reasoning' => 'Production repos.']],
            'claims' => [[
                'claim' => 'Led the payments rewrite',
                'status' => 'weak',
                'evidence' => 'Led the payments rewrite at Acme.',
                'source' => 'cv',
                'source_ref' => '',
            ]],
            'interview_questions' => [[
                'question' => 'Walk me through the ledger posting model.',
                'targets_skill' => 'Laravel',
                'grounded_in' => 'jamierivera/ledger/src/Posting.php:L1-L1',
                'ideal_answer_points' => ['Double-entry invariants'],
            ]],
        ];
    }

    /**
     * Fake the GitHub endpoints the controller reaches for one candidate.
     */
    protected function fakeGitHub(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(['login' => 'jamierivera', 'public_repos' => 24]),
            'api.github.com/users/jamierivera/repos*' => Http::response([
                ['name' => 'ledger', 'description' => 'Double-entry ledger', 'language' => 'PHP', 'stargazers_count' => 91],
                ['name' => 'notes', 'description' => 'Scratch notes', 'language' => 'PHP', 'stargazers_count' => 3],
            ]),
            'api.github.com/repos/jamierivera/ledger' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/jamierivera/ledger/git/trees/*' => Http::response([
                'tree' => [
                    ['path' => 'src/Posting.php', 'type' => 'blob', 'size' => 4000],
                    ['path' => 'src/Ledger.php', 'type' => 'blob', 'size' => 2000],
                ],
            ]),
            'api.github.com/repos/jamierivera/ledger/contents/src/Posting.php*' => Http::response([
                'content' => base64_encode('final class Posting {}'),
                'encoding' => 'base64',
            ]),
            'api.github.com/repos/jamierivera/ledger/contents/src/Ledger.php*' => Http::response([
                'content' => base64_encode('final class Ledger {}'),
                'encoding' => 'base64',
            ]),
        ]);
    }
}
