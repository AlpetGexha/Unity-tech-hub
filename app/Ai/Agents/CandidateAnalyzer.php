<?php

namespace App\Ai\Agents;

use App\Models\JobOpening;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Stringable;

/**
 * Scores one candidate against one job opening in a single structured call.
 *
 * The agent generates candidate context and judgement fields — `cv_summary`,
 * `overall`, `skills`, `claims` and `interview_questions`. Metadata (`candidate_id`, `job_id`, `generated_at`,
 * `rubric`, `fairness_note`) is merged in PHP after the call so the wording and
 * timestamps are ours, never the model's.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Temperature(0)]
#[Strict]
class CandidateAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The scoring rubric. Kept first and byte-identical in every prompt so the provider caches the prefix.
     */
    public const RUBRIC = <<<'RUBRIC'
        You are a technical hiring analyst. You score one candidate against one job opening and you
        justify every judgement with evidence the reader can check for themselves.

        SCORING RUBRIC
        - fit_score (0-100): how well the evidence supports this candidate doing this job.
          80-100 strong_yes, 60-79 yes, 40-59 maybe, 0-39 no. The recommendation must match the band.
        - skills[].score (0-100): demonstrated ability in that skill, judged from evidence only.
        - skills[].weight (0-100): how much that skill matters for THIS job, taken from the job template below.
        - Score what the evidence shows, not what the candidate asserts. A claim with no supporting
          artifact caps the related skill score at 50.

        EVIDENCE RULES
        - Every claim you list must quote the source verbatim in `evidence`. Never paraphrase a quote.
        - `source` says where the quote came from: `cv`, `github_profile`, `repo`, or `source_file`.
        - `source_ref` locates it: "user/repo/path:42" for code, "" when there is no locatable reference.
        - Mark a claim `verified` only when the quote directly supports it, `weak` when the support is
          indirect or partial, and `unsupported` when nothing in the provided material backs it.
        - Never invent a repository, file, line number or quote. If you did not read it, it does not exist.

        INTERVIEW QUESTIONS
        - Write questions that probe the weakest or least verified areas first.
        - `grounded_in` is either a concrete location ("user/repo/path:L10-L38") or "job_requirement".
        - `ideal_answer_points` lists the specific things a strong answer would cover.

        FAIRNESS
        - Judge skills and evidence only. Never use or refer to age, gender, ethnicity, nationality,
          religion, marital status, photographs, or names as signals of ability.

        OUTPUT
        - Return every field of the schema. Use "" rather than omitting a string you cannot fill.
        RUBRIC;

    /**
     * Requirements used when the job template leaves them blank.
     */
    public const DEFAULT_REQUIREMENTS = 'No requirements were provided. Assess general software engineering ability: language and framework depth, testing, code quality, and system design.';

    /**
     * Priorities used when the job template leaves them blank.
     */
    public const DEFAULT_PRIORITIES = 'No priorities were provided. Weight demonstrated, verifiable delivery above self-reported experience.';

    public function __construct(public JobOpening $job) {}

    /**
     * Analyse one candidate and return the model-generated keys of the contract.
     *
     * The SDK's `prompt()` is typed to the base `AgentResponse`, so this narrows it once
     * here rather than at every call site. Reaching the throw would mean the provider
     * ignored a strict schema, which callers should hear about rather than silently store.
     *
     * @return array<string, mixed>
     */
    public function analyse(string $context): array
    {
        $response = $this->prompt($context);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The analyzer returned an unstructured response.');
        }

        return $response->toArray();
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return self::RUBRIC."\n\n".$this->jobTemplate();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'cv_summary' => $schema->string()
                ->description('A concise, factual two to four sentence summary of the candidate CV, covering background, experience, and notable projects. Do not include a hiring recommendation.')->required(),

            'overall' => $schema->object(fn (JsonSchema $schema): array => [
                'fit_score' => $schema->integer()->min(0)->max(100)
                    ->description('Overall fit for this job, 0-100.')->required(),
                'recommendation' => $schema->string()->enum(['strong_yes', 'yes', 'maybe', 'no'])
                    ->description('Hiring recommendation matching the fit_score band.')->required(),
                'summary' => $schema->string()
                    ->description('Two to three sentences justifying the score.')->required(),
            ])->required(),

            'skills' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'name' => $schema->string()->description('The skill being scored.')->required(),
                    'score' => $schema->integer()->min(0)->max(100)
                        ->description('Demonstrated ability in this skill, 0-100.')->required(),
                    'weight' => $schema->integer()->min(0)->max(100)
                        ->description('How much this skill matters for this job, 0-100.')->required(),
                    'reasoning' => $schema->string()->description('Why this score, citing evidence.')->required(),
                ])
            )->description('One entry per skill the job template cares about.')->required(),

            'claims' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'claim' => $schema->string()->description('A claim the candidate made.')->required(),
                    'status' => $schema->string()->enum(['verified', 'weak', 'unsupported'])
                        ->description('How well the evidence supports the claim.')->required(),
                    'evidence' => $schema->string()
                        ->description('Exact quote from the CV, GitHub profile, repo, or source file.')->required(),
                    'source' => $schema->string()->enum(['cv', 'github_profile', 'repo', 'source_file'])
                        ->description('Where the quote came from.')->required(),
                    'source_ref' => $schema->string()
                        ->description('Location such as "user/repo/path:42", or "" when there is none.')->required(),
                ])
            )->description('Candidate claims checked against the supplied material.')->required(),

            'interview_questions' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'question' => $schema->string()->description('The question to ask.')->required(),
                    'targets_skill' => $schema->string()->description('The skill this question probes.')->required(),
                    'grounded_in' => $schema->string()
                        ->description('"user/repo/path:L10-L38" or "job_requirement".')->required(),
                    'ideal_answer_points' => $schema->array()->items($schema->string())
                        ->description('What a strong answer would cover.')->required(),
                ])
            )->description('Questions probing the least verified areas first.')->required(),
        ];
    }

    /**
     * Render the job template section of the instructions.
     */
    protected function jobTemplate(): string
    {
        $title = trim($this->job->title) !== '' ? trim($this->job->title) : 'Unspecified role';
        $requirements = trim((string) $this->job->requirements);
        $priorities = trim((string) $this->job->priorities);

        return implode("\n", [
            'JOB TEMPLATE',
            'Title: '.$title,
            '',
            'Requirements:',
            $requirements !== '' ? $requirements : self::DEFAULT_REQUIREMENTS,
            '',
            'What this team cares about:',
            $priorities !== '' ? $priorities : self::DEFAULT_PRIORITIES,
        ]);
    }
}
