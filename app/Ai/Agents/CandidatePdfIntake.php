<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Stringable;

/**
 * Extracts candidate profile fields from an uploaded PDF in one model call.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
#[MaxTokens(2048)]
#[Temperature(0)]
#[Strict]
class CandidatePdfIntake implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Extract profile fields from a candidate PDF.
     *
     * @return array{name: string, github_username: string, linkedin_text: string}
     */
    public function extract(UploadedFile $file): array
    {
        $response = $this->prompt(
            'Extract the candidate profile fields from the attached CV.',
            attachments: [Document::fromUpload($file)],
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The candidate PDF intake returned an unstructured response.');
        }

        $result = $response->toArray();

        return [
            'name' => (string) $result['name'],
            'github_username' => (string) $result['github_username'],
            'linkedin_text' => (string) $result['linkedin_text'],
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You extract candidate profile fields from an attached CV PDF for an HR intake form.',
            'Extract the candidate full name, GitHub username, and LinkedIn profile text or URL only when explicitly present.',
            'Return an empty string for a missing profile field; never guess usernames or links.',
            'For github_username, return only the username without the URL or @ symbol.',
            'For linkedin_text, preserve the supplied profile URL and/or profile text.',
        ]);
    }

    /**
     * Get the structured output schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The candidate full name, or an empty string if absent.')->required(),
            'github_username' => $schema->string()
                ->description('GitHub username only, or an empty string if absent.')->required(),
            'linkedin_text' => $schema->string()
                ->description('LinkedIn profile URL or supplied profile text, or an empty string if absent.')->required(),
        ];
    }
}
