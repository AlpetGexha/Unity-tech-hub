<?php

namespace Tests\Feature;

use App\Services\GitHubClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use SplFileInfo;
use Tests\TestCase;

class GitHubClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(GitHubClient::cachePath());

        Http::preventStrayRequests();

        config(['services.github.token' => 'test-token']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(GitHubClient::cachePath());

        parent::tearDown();
    }

    public function test_a_repeated_profile_lookup_is_served_from_disk(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(['login' => 'jamierivera', 'public_repos' => 24]),
        ]);

        $first = GitHubClient::profile('jamierivera');
        $second = GitHubClient::profile('jamierivera');

        Http::assertSentCount(1);
        $this->assertSame(['login' => 'jamierivera', 'public_repos' => 24], $first);
        $this->assertSame($first, $second);
    }

    public function test_the_cached_response_is_written_to_disk_so_it_survives_the_process(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(['login' => 'jamierivera']),
        ]);

        GitHubClient::profile('jamierivera');

        $files = $this->cachedFiles();

        $this->assertCount(1, $files);
        $this->assertSame('json', $files[0]->getExtension());
        $this->assertSame(['login' => 'jamierivera'], json_decode(File::get($files[0]->getPathname()), true));
    }

    public function test_requests_are_authenticated_with_the_configured_token(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(['login' => 'jamierivera']),
        ]);

        GitHubClient::profile('jamierivera');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Accept', 'application/vnd.github+json'));
    }

    public function test_requests_are_sent_unauthenticated_when_no_token_is_configured(): void
    {
        config(['services.github.token' => null]);

        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(['login' => 'jamierivera']),
        ]);

        GitHubClient::profile('jamierivera');

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_an_unknown_user_returns_empty_and_is_not_cached(): void
    {
        Http::fake([
            'api.github.com/users/ghost' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->assertSame([], GitHubClient::profile('ghost'));
        $this->assertSame([], $this->cachedFiles());
    }

    public function test_a_rate_limited_response_returns_empty_rather_than_throwing(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera' => Http::response(
                ['message' => 'API rate limit exceeded'],
                403,
                ['X-RateLimit-Remaining' => '0'],
            ),
            'api.github.com/users/jamierivera/repos*' => Http::response(
                ['message' => 'API rate limit exceeded'],
                403,
                ['X-RateLimit-Remaining' => '0'],
            ),
        ]);

        $this->assertSame([], GitHubClient::profile('jamierivera'));
        $this->assertSame([], GitHubClient::topRepos('jamierivera'));
    }

    public function test_a_connection_failure_returns_empty_rather_than_throwing(): void
    {
        Http::fake(fn (): never => throw new ConnectionException('Could not resolve host.'));

        $this->assertSame([], GitHubClient::profile('jamierivera'));
    }

    public function test_top_repos_returns_the_most_starred_repositories_first(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera/repos*' => Http::response([
                ['name' => 'notes', 'stargazers_count' => 3],
                ['name' => 'ledger', 'stargazers_count' => 91],
                ['name' => 'dotfiles', 'stargazers_count' => 12],
                ['name' => 'scratch', 'stargazers_count' => 0],
            ]),
        ]);

        $repos = GitHubClient::topRepos('jamierivera');

        $this->assertSame(['ledger', 'dotfiles', 'notes'], array_column($repos, 'name'));
    }

    public function test_top_repos_honours_the_requested_count(): void
    {
        Http::fake([
            'api.github.com/users/jamierivera/repos*' => Http::response([
                ['name' => 'ledger', 'stargazers_count' => 91],
                ['name' => 'dotfiles', 'stargazers_count' => 12],
            ]),
        ]);

        $this->assertCount(1, GitHubClient::topRepos('jamierivera', 1));
    }

    public function test_source_files_are_returned_with_a_user_repo_path_reference(): void
    {
        $this->fakeRepositoryTree();

        $sources = GitHubClient::sourceFiles('jamierivera', 'ledger');

        $this->assertSame(
            ['jamierivera/ledger/src/Posting.php', 'jamierivera/ledger/src/Ledger.php'],
            array_column($sources, 'path'),
        );
        $this->assertSame('final class Posting {}', $sources[0]['content']);
    }

    public function test_source_files_skip_dependencies_and_non_source_noise(): void
    {
        $this->fakeRepositoryTree();

        $paths = array_column(GitHubClient::sourceFiles('jamierivera', 'ledger', 10), 'path');

        $this->assertNotContains('jamierivera/ledger/vendor/big/Package.php', $paths);
        $this->assertNotContains('jamierivera/ledger/README.md', $paths);
        $this->assertNotContains('jamierivera/ledger/composer.lock', $paths);
    }

    public function test_source_files_return_empty_for_a_missing_repository(): void
    {
        Http::fake([
            'api.github.com/repos/jamierivera/ghost' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->assertSame([], GitHubClient::sourceFiles('jamierivera', 'ghost'));
    }

    public function test_a_second_source_file_fetch_makes_no_further_requests(): void
    {
        $this->fakeRepositoryTree();

        GitHubClient::sourceFiles('jamierivera', 'ledger');
        $requestsAfterFirstCall = Http::recorded()->count();

        GitHubClient::sourceFiles('jamierivera', 'ledger');

        Http::assertSentCount($requestsAfterFirstCall);
    }

    /**
     * Get the files currently sitting in the GitHub disk cache.
     *
     * @return array<int, SplFileInfo>
     */
    protected function cachedFiles(): array
    {
        return File::isDirectory(GitHubClient::cachePath())
            ? array_values(File::files(GitHubClient::cachePath()))
            : [];
    }

    /**
     * Fake the repository metadata, tree, and blob endpoints for a small PHP project.
     */
    protected function fakeRepositoryTree(): void
    {
        Http::fake([
            'api.github.com/repos/jamierivera/ledger' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/jamierivera/ledger/git/trees/*' => Http::response([
                'tree' => [
                    ['path' => 'README.md', 'type' => 'blob', 'size' => 9000],
                    ['path' => 'composer.lock', 'type' => 'blob', 'size' => 80000],
                    ['path' => 'vendor/big/Package.php', 'type' => 'blob', 'size' => 50000],
                    ['path' => 'src/Ledger.php', 'type' => 'blob', 'size' => 2000],
                    ['path' => 'src/Posting.php', 'type' => 'blob', 'size' => 4000],
                    ['path' => 'src', 'type' => 'tree'],
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
