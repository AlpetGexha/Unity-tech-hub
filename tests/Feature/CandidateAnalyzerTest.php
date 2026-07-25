<?php

namespace Tests\Feature;

use App\Ai\Agents\CandidateAnalyzer;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;
use ReflectionClass;
use Tests\TestCase;

class CandidateAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyzer_result_matches_contract(): void
    {
        CandidateAnalyzer::fake();

        $job = JobOpening::factory()->create();

        $response = (new CandidateAnalyzer($job))->prompt('ctx');

        $this->assertIsInt($response['overall']['fit_score']);
        $this->assertIsString($response['cv_summary']);
        $this->assertContains($response['overall']['recommendation'], ['strong_yes', 'yes', 'maybe', 'no']);
        $this->assertIsString($response['overall']['summary']);

        $this->assertNotEmpty($response['skills']);
        $this->assertIsString($response['skills'][0]['name']);
        $this->assertIsInt($response['skills'][0]['score']);
        $this->assertIsInt($response['skills'][0]['weight']);
        $this->assertIsString($response['skills'][0]['reasoning']);

        $this->assertNotEmpty($response['claims']);
        $this->assertContains($response['claims'][0]['status'], ['verified', 'weak', 'unsupported']);
        $this->assertContains($response['claims'][0]['source'], ['cv', 'github_profile', 'repo', 'source_file']);
        $this->assertIsString($response['claims'][0]['evidence']);
        $this->assertIsString($response['claims'][0]['source_ref']);

        $this->assertNotEmpty($response['interview_questions']);
        $this->assertIsString($response['interview_questions'][0]['question']);
        $this->assertIsString($response['interview_questions'][0]['targets_skill']);
        $this->assertIsString($response['interview_questions'][0]['grounded_in']);
        $this->assertNotEmpty($response['interview_questions'][0]['ideal_answer_points']);

        $this->assertArrayNotHasKey('generated_at', $response->toArray());
        $this->assertArrayNotHasKey('fairness_note', $response->toArray());

        CandidateAnalyzer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('ctx'));
    }

    public function test_analyse_hands_back_the_structured_array_the_contract_is_built_from(): void
    {
        CandidateAnalyzer::fake();

        $job = JobOpening::factory()->create();

        $result = (new CandidateAnalyzer($job))->analyse('ctx');

        $this->assertSame(
            ['cv_summary', 'overall', 'skills', 'claims', 'interview_questions'],
            array_keys($result),
        );
    }

    public function test_schema_declares_only_the_model_generated_keys(): void
    {
        $job = JobOpening::factory()->create();

        $schema = (new CandidateAnalyzer($job))->schema(new JsonSchemaTypeFactory);

        $this->assertSame(
            ['cv_summary', 'overall', 'skills', 'claims', 'interview_questions'],
            array_keys($schema),
        );
    }

    public function test_every_schema_field_is_required(): void
    {
        $job = JobOpening::factory()->create();

        $schema = (new CandidateAnalyzer($job))->schema(new JsonSchemaTypeFactory);

        $serialized = (new JsonSchemaTypeFactory)->object($schema)->toArray();

        $this->assertSame(['cv_summary', 'overall', 'skills', 'claims', 'interview_questions'], $serialized['required']);
        $this->assertSame(
            ['fit_score', 'recommendation', 'summary'],
            $serialized['properties']['overall']['required'],
        );
        $this->assertSame(
            ['name', 'score', 'weight', 'reasoning'],
            $serialized['properties']['skills']['items']['required'],
        );
        $this->assertSame(
            ['claim', 'status', 'evidence', 'source', 'source_ref'],
            $serialized['properties']['claims']['items']['required'],
        );
        $this->assertSame(
            ['question', 'targets_skill', 'grounded_in', 'ideal_answer_points'],
            $serialized['properties']['interview_questions']['items']['required'],
        );
    }

    public function test_instructions_lead_with_the_rubric_then_the_job_template(): void
    {
        $job = JobOpening::factory()->create([
            'title' => 'Senior Laravel Engineer',
            'requirements' => 'Five years of PHP. Queue experience.',
            'priorities' => 'Ships evidence, not buzzwords.',
        ]);

        $instructions = (string) (new CandidateAnalyzer($job))->instructions();

        $this->assertStringStartsWith(CandidateAnalyzer::RUBRIC, $instructions);
        $this->assertStringContainsString('Senior Laravel Engineer', $instructions);
        $this->assertStringContainsString('Five years of PHP. Queue experience.', $instructions);
        $this->assertStringContainsString('Ships evidence, not buzzwords.', $instructions);
    }

    public function test_instructions_fall_back_to_defaults_when_the_job_template_is_empty(): void
    {
        $job = JobOpening::factory()->create([
            'title' => 'Backend Engineer',
            'requirements' => '   ',
            'priorities' => '',
        ]);

        $instructions = (string) (new CandidateAnalyzer($job))->instructions();

        $this->assertStringStartsWith(CandidateAnalyzer::RUBRIC, $instructions);
        $this->assertStringContainsString(CandidateAnalyzer::DEFAULT_REQUIREMENTS, $instructions);
        $this->assertStringContainsString(CandidateAnalyzer::DEFAULT_PRIORITIES, $instructions);
    }

    public function test_instructions_are_identical_across_candidates_of_the_same_job(): void
    {
        $job = JobOpening::factory()->create();

        $this->assertSame(
            (string) (new CandidateAnalyzer($job))->instructions(),
            (string) (new CandidateAnalyzer($job->fresh()))->instructions(),
        );
    }

    public function test_the_candidate_context_is_the_only_thing_sent_in_the_prompt(): void
    {
        CandidateAnalyzer::fake();

        $job = JobOpening::factory()->create();

        (new CandidateAnalyzer($job))->prompt('CANDIDATE: Jamie Rivera');

        CandidateAnalyzer::assertPrompted('CANDIDATE: Jamie Rivera');
        CandidateAnalyzer::assertNotPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains(CandidateAnalyzer::RUBRIC)
        );
    }

    public function test_the_agent_is_configured_for_deterministic_strict_openai_output(): void
    {
        $reflection = new ReflectionClass(CandidateAnalyzer::class);

        $this->assertSame(Lab::OpenAI, $reflection->getAttributes(Provider::class)[0]->newInstance()->value);
        $this->assertSame('gpt-4o', $reflection->getAttributes(ModelAttribute::class)[0]->newInstance()->value);
        $this->assertSame(0.0, (float) $reflection->getAttributes(Temperature::class)[0]->newInstance()->value);
        $this->assertTrue(Strict::isAppliedTo(new CandidateAnalyzer(JobOpening::factory()->create())));
    }
}
