<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads public GitHub data through the REST API, never by cloning.
 *
 * Every response is cached to disk under `storage/app/cache/github/`, which is what
 * keeps repeated rehearsal runs off the hourly rate limit and lets a seeded demo
 * work with no network at all. Failures are never cached, so a later run can retry.
 */
class GitHubClient
{
    /**
     * The GitHub REST API root.
     */
    public const API = 'https://api.github.com';

    /**
     * File extensions worth showing to the analyzer as evidence.
     */
    public const SOURCE_EXTENSIONS = [
        'php', 'js', 'jsx', 'ts', 'tsx', 'py', 'go', 'rb', 'rs',
        'java', 'kt', 'swift', 'cs', 'ex', 'exs', 'scala', 'vue',
    ];

    /**
     * Path fragments that mean a file is a dependency, a build artifact, or generated.
     */
    public const IGNORED_PATH_FRAGMENTS = [
        'vendor/', 'node_modules/', 'dist/', 'build/', 'storage/',
        '.min.', 'bootstrap/cache/', '__pycache__/',
    ];

    /**
     * The largest blob worth fetching. The contents endpoint stops inlining content well before 1MB.
     */
    public const MAX_BLOB_BYTES = 200000;

    /**
     * Get a user's public profile, or an empty array when it cannot be read.
     *
     * @return array<string, mixed>
     */
    public static function profile(string $user): array
    {
        return self::get(self::API.'/users/'.rawurlencode($user)) ?? [];
    }

    /**
     * Get a user's most starred public repositories.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function topRepos(string $user, int $n = 3): array
    {
        $repos = self::get(self::API.'/users/'.rawurlencode($user).'/repos?per_page=100&sort=updated&type=owner');

        if (blank($repos)) {
            return [];
        }

        usort($repos, fn (array $a, array $b): int => ($b['stargazers_count'] ?? 0) <=> ($a['stargazers_count'] ?? 0));

        return array_slice($repos, 0, $n);
    }

    /**
     * Get the largest source files from a repository, newest default branch first.
     *
     * @return array<int, array{path: string, content: string}>
     */
    public static function sourceFiles(string $user, string $repo, int $n = 2): array
    {
        $repository = self::get(self::API.'/repos/'.rawurlencode($user).'/'.rawurlencode($repo));

        if (blank($repository)) {
            return [];
        }

        $branch = $repository['default_branch'] ?? 'main';

        $tree = self::get(self::API.'/repos/'.rawurlencode($user).'/'.rawurlencode($repo).'/git/trees/'.rawurlencode($branch).'?recursive=1');

        $files = [];

        foreach (self::rankSourceBlobs($tree['tree'] ?? [], $n) as $blob) {
            $content = self::blobContent($user, $repo, $blob['path'], $branch);

            if ($content === null) {
                continue;
            }

            $files[] = [
                'path' => $user.'/'.$repo.'/'.$blob['path'],
                'content' => $content,
            ];
        }

        return $files;
    }

    /**
     * Get the directory the disk cache writes to.
     */
    public static function cachePath(): string
    {
        return storage_path('app/cache/github');
    }

    /**
     * Pick the largest source blobs in a repository tree.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array{path: string, size: int}>
     */
    protected static function rankSourceBlobs(array $tree, int $n): array
    {
        $blobs = [];

        foreach ($tree as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $size = (int) ($entry['size'] ?? 0);

            if (($entry['type'] ?? '') !== 'blob' || $path === '' || $size > self::MAX_BLOB_BYTES) {
                continue;
            }

            if (! in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), self::SOURCE_EXTENSIONS, true)) {
                continue;
            }

            if (Str::contains($path, self::IGNORED_PATH_FRAGMENTS)) {
                continue;
            }

            $blobs[] = ['path' => $path, 'size' => $size];
        }

        usort($blobs, fn (array $a, array $b): int => $b['size'] <=> $a['size']);

        return array_slice($blobs, 0, $n);
    }

    /**
     * Read one file's decoded contents, or null when it cannot be read.
     */
    protected static function blobContent(string $user, string $repo, string $path, string $branch): ?string
    {
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        $blob = self::get(
            self::API.'/repos/'.rawurlencode($user).'/'.rawurlencode($repo).'/contents/'.$encodedPath.'?ref='.rawurlencode($branch)
        );

        if (($blob['encoding'] ?? null) !== 'base64' || blank($blob['content'] ?? null)) {
            return null;
        }

        $decoded = base64_decode((string) $blob['content'], true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Fetch a URL, serving it from the disk cache when it has been seen before.
     *
     * A 404 (unknown user or repo), a 403 (rate limit), and a dead connection all return
     * null and write nothing, so the caller degrades instead of crashing the demo.
     *
     * @return array<array-key, mixed>|null
     */
    protected static function get(string $url): ?array
    {
        $cacheFile = self::cachePath().DIRECTORY_SEPARATOR.sha1($url).'.json';

        if (File::exists($cacheFile)) {
            $cached = json_decode(File::get($cacheFile), true);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = config('services.github.token');

        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->timeout(15);

        if (filled($token)) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        File::ensureDirectoryExists(self::cachePath());
        File::put($cacheFile, (string) json_encode($payload));

        return $payload;
    }
}
