<?php

namespace Tests\Feature;

use App\Ai\Agents\InterviewGrader;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;
use ReflectionClass;
use Tests\TestCase;

class InterviewGraderTest extends TestCase
{
    public function test_grader_result_matches_schema(): void
    {
        InterviewGrader::fake();

        $grader = new InterviewGrader(
            question: 'How would you scale a queue-heavy Laravel app?',
            idealAnswerPoints: ['Mentions horizontal scaling', 'Mentions queue prioritisation'],
        );

        $response = $grader->prompt('The candidate answered: I would add more workers.');

        $this->assertIsInt($response['score']);
        $this->assertGreaterThanOrEqual(0, $response['score']);
        $this->assertLessThanOrEqual(100, $response['score']);
        $this->assertIsString($response['notes']);

        InterviewGrader::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('I would add more workers.')
        );
    }

    public function test_schema_declares_only_score_and_notes(): void
    {
        $grader = new InterviewGrader(
            question: 'Question?',
            idealAnswerPoints: ['Point one'],
        );

        $schema = $grader->schema(new JsonSchemaTypeFactory);

        $this->assertSame(['score', 'notes'], array_keys($schema));
    }

    public function test_every_schema_field_is_required(): void
    {
        $grader = new InterviewGrader(
            question: 'Question?',
            idealAnswerPoints: ['Point one'],
        );

        $serialized = (new JsonSchemaTypeFactory)->object($grader->schema(new JsonSchemaTypeFactory))->toArray();

        $this->assertSame(['score', 'notes'], $serialized['required']);
    }

    public function test_instructions_include_question_and_ideal_answer_points(): void
    {
        $grader = new InterviewGrader(
            question: 'How would you scale a queue-heavy Laravel app?',
            idealAnswerPoints: ['Mentions horizontal scaling', 'Mentions queue prioritisation'],
        );

        $instructions = (string) $grader->instructions();

        $this->assertStringContainsString('How would you scale a queue-heavy Laravel app?', $instructions);
        $this->assertStringContainsString('Mentions horizontal scaling', $instructions);
        $this->assertStringContainsString('Mentions queue prioritisation', $instructions);
    }

    public function test_the_agent_is_configured_for_deterministic_strict_openai_output(): void
    {
        $reflection = new ReflectionClass(InterviewGrader::class);

        $this->assertSame(Lab::OpenAI, $reflection->getAttributes(Provider::class)[0]->newInstance()->value);
        $this->assertSame('gpt-4o', $reflection->getAttributes(ModelAttribute::class)[0]->newInstance()->value);
        $this->assertSame(0.0, (float) $reflection->getAttributes(Temperature::class)[0]->newInstance()->value);

        $grader = new InterviewGrader(question: 'Question?', idealAnswerPoints: ['Point one']);

        $this->assertTrue(Strict::isAppliedTo($grader));
    }
}
