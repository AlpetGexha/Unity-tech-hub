<?php

namespace App\Ai\Agents;

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
 * Scores one interview answer against one question's ideal answer points.
 *
 * The agent generates `score` and `notes` only. The question and its ideal
 * answer points are fixed inputs baked into `instructions()`, not the prompt.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Temperature(0)]
#[Strict]
class InterviewGrader implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<int, string>  $idealAnswerPoints
     */
    public function __construct(
        public string $question,
        public array $idealAnswerPoints,
    ) {}

    /**
     * Grade a transcribed answer and return the `score` and `notes` keys of the contract.
     *
     * The SDK's `prompt()` is typed to the base `AgentResponse`, so this narrows it once
     * here rather than at every call site. Reaching the throw would mean the provider
     * ignored a strict schema, which callers should hear about rather than silently store.
     *
     * @return array<string, mixed>
     */
    public function grade(string $transcript): array
    {
        $response = $this->prompt($transcript);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The grader returned an unstructured response.');
        }

        return $response->toArray();
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You are a technical interviewer. Score the candidate\'s spoken answer to the question',
            'below against the ideal answer points, 0-100. Judge only what the answer demonstrates;',
            'do not penalise phrasing, accent, or brevity. Give a short, specific note explaining the score.',
            '',
            'QUESTION',
            $this->question,
            '',
            'IDEAL ANSWER POINTS',
            ...array_map(fn (string $point): string => '- '.$point, $this->idealAnswerPoints),
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->min(0)->max(100)
                ->description('How well the answer covers the ideal answer points, 0-100.')->required(),
            'notes' => $schema->string()
                ->description('A short, specific note explaining the score.')->required(),
        ];
    }
}
