<?php

namespace App\Services;

use App\Models\Candidate;

/**
 * Assembles the per-candidate half of the analyzer prompt.
 *
 * Only this string varies between candidates — the rubric and job template live
 * in the agent's instructions so the provider can cache that prefix.
 */
class CandidateContext
{
    /**
     * The most characters of any single source file that are worth sending.
     */
    public const MAX_SOURCE_CHARS = 4000;

    /**
     * Build the prompt payload describing one candidate.
     *
     * GitHub data is passed in rather than fetched here so the caller controls
     * caching and can degrade gracefully when the GitHub API is unavailable.
     *
     * @param  array{profile?: array<string, mixed>|null, repos?: array<int, array<string, mixed>>, sources?: array<int, array{path: string, content: string}>}  $github
     */
    public static function build(Candidate $candidate, array $github = []): string
    {
        return implode("\n\n", [
            'CANDIDATE: '.$candidate->name,
            self::curriculumVitae($candidate),
            self::linkedIn($candidate),
            self::gitHubProfile($candidate, $github['profile'] ?? null),
            self::repositories($github['repos'] ?? []),
            self::sourceFiles($github['sources'] ?? []),
        ]);
    }

    /**
     * Render the CV section from the markdown converted from the uploaded PDF.
     */
    protected static function curriculumVitae(Candidate $candidate): string
    {
        $cvMarkdown = trim((string) $candidate->cv_markdown);

        if ($cvMarkdown !== '') {
            return "CV:\n".$cvMarkdown;
        }

        return 'CV: No CV was provided.';
    }

    /**
     * Render the pasted LinkedIn section.
     */
    protected static function linkedIn(Candidate $candidate): string
    {
        $linkedIn = trim((string) $candidate->linkedin_text);

        return $linkedIn !== ''
            ? "LINKEDIN (pasted by the candidate):\n".$linkedIn
            : 'LINKEDIN: No LinkedIn text was provided.';
    }

    /**
     * Render the GitHub profile section.
     *
     * @param  array<string, mixed>|null  $profile
     */
    protected static function gitHubProfile(Candidate $candidate, ?array $profile): string
    {
        $username = trim((string) $candidate->github_username);

        if ($username === '') {
            return 'GITHUB PROFILE: No GitHub username was provided.';
        }

        if (blank($profile)) {
            return 'GITHUB PROFILE: the profile for '.$username.' could not be fetched.';
        }

        return 'GITHUB PROFILE ('.$username."):\n".self::json($profile);
    }

    /**
     * Render the top repositories section.
     *
     * @param  array<int, array<string, mixed>>  $repos
     */
    protected static function repositories(array $repos): string
    {
        if ($repos === []) {
            return 'TOP REPOSITORIES: none were fetched.';
        }

        $lines = array_map(static function (array $repo): string {
            $parts = array_filter([
                (string) ($repo['name'] ?? 'unnamed'),
                filled($repo['language'] ?? null) ? 'language: '.$repo['language'] : null,
                isset($repo['stargazers_count']) ? 'stars: '.$repo['stargazers_count'] : null,
                filled($repo['description'] ?? null) ? $repo['description'] : null,
            ]);

            return '- '.implode(' — ', $parts);
        }, $repos);

        return "TOP REPOSITORIES:\n".implode("\n", $lines);
    }

    /**
     * Render the fetched source snippets section.
     *
     * @param  array<int, array{path: string, content: string}>  $sources
     */
    protected static function sourceFiles(array $sources): string
    {
        if ($sources === []) {
            return 'SOURCE FILES: none were fetched. Claims that would need code to verify are at best weak.';
        }

        $snippets = array_map(static function (array $source): string {
            $content = (string) $source['content'];

            if (mb_strlen($content) > self::MAX_SOURCE_CHARS) {
                $content = mb_substr($content, 0, self::MAX_SOURCE_CHARS)."\n[truncated]";
            }

            return $source['path']."\n```\n".$content."\n```";
        }, $sources);

        return "SOURCE FILES:\n".implode("\n\n", $snippets);
    }

    /**
     * Pretty print a fetched payload for the prompt.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
