<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Services\CandidateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_the_pasted_cv_text(): void
    {
        $candidate = Candidate::factory()->create([
            'name' => 'Jamie Rivera',
            'cv_text' => 'Led the payments rewrite at Acme.',
            'cv_path' => null,
            'linkedin_text' => 'Open to backend roles.',
        ]);

        $context = CandidateContext::build($candidate);

        $this->assertStringContainsString('Jamie Rivera', $context);
        $this->assertStringContainsString('Led the payments rewrite at Acme.', $context);
        $this->assertStringContainsString('Open to backend roles.', $context);
    }

    public function test_it_notes_the_attachment_when_the_cv_was_uploaded_instead_of_pasted(): void
    {
        $candidate = Candidate::factory()->create([
            'cv_text' => '',
            'cv_path' => 'cv/jamie-rivera.pdf',
        ]);

        $context = CandidateContext::build($candidate);

        $this->assertStringContainsString('see attachment', $context);
        $this->assertStringContainsString('cv/jamie-rivera.pdf', $context);
    }

    public function test_it_reports_a_missing_cv_rather_than_leaving_the_section_blank(): void
    {
        $candidate = Candidate::factory()->create([
            'cv_text' => '',
            'cv_path' => null,
        ]);

        $context = CandidateContext::build($candidate);

        $this->assertStringContainsString('No CV was provided.', $context);
        $this->assertStringNotContainsString('see attachment', $context);
    }

    public function test_it_renders_the_github_profile_repos_and_source_snippets(): void
    {
        $candidate = Candidate::factory()->create(['github_username' => 'jamierivera']);

        $context = CandidateContext::build($candidate, [
            'profile' => ['login' => 'jamierivera', 'public_repos' => 24],
            'repos' => [
                ['name' => 'ledger', 'description' => 'Double-entry ledger', 'language' => 'PHP', 'stargazers_count' => 91],
            ],
            'sources' => [
                ['path' => 'jamierivera/ledger/src/Posting.php', 'content' => 'final class Posting {}'],
            ],
        ]);

        $this->assertStringContainsString('"login": "jamierivera"', $context);
        $this->assertStringContainsString('"public_repos": 24', $context);
        $this->assertStringContainsString('ledger', $context);
        $this->assertStringContainsString('Double-entry ledger', $context);
        $this->assertStringContainsString('jamierivera/ledger/src/Posting.php', $context);
        $this->assertStringContainsString('final class Posting {}', $context);
    }

    public function test_it_states_that_github_data_is_unavailable_when_nothing_was_fetched(): void
    {
        $candidate = Candidate::factory()->create(['github_username' => 'jamierivera']);

        $context = CandidateContext::build($candidate);

        $this->assertStringContainsString('jamierivera', $context);
        $this->assertStringContainsString('could not be fetched', $context);
    }

    public function test_it_truncates_oversized_source_snippets(): void
    {
        $candidate = Candidate::factory()->create();

        $context = CandidateContext::build($candidate, [
            'sources' => [
                ['path' => 'user/repo/huge.php', 'content' => str_repeat('a', CandidateContext::MAX_SOURCE_CHARS + 500)],
            ],
        ]);

        $this->assertStringContainsString('[truncated]', $context);
        $this->assertLessThan(CandidateContext::MAX_SOURCE_CHARS + 2000, strlen($context));
    }
}
