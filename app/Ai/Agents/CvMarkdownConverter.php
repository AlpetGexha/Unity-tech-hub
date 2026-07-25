<?php

namespace App\Ai\Agents;

use Illuminate\Http\UploadedFile;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Converts an uploaded CV/resume PDF into clean, well-structured markdown.
 *
 * This is a plain-text agent, not a structured one: the whole response is the
 * converted markdown, so there is no schema to define.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Temperature(0)]
class CvMarkdownConverter implements Agent
{
    use Promptable;

    /**
     * Convert an uploaded CV/resume PDF into markdown.
     */
    public function convert(UploadedFile $file): string
    {
        return (string) $this->prompt(
            'Convert the attached CV to markdown.',
            attachments: [Document::fromUpload($file)],
        );
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You convert an attached CV/resume PDF into clean, well-structured markdown.',
            'Preserve all factual content faithfully: roles, dates, employers, skills, projects, and links.',
            'Do not summarize, invent, or omit anything from the source document.',
            'Use markdown headings and lists appropriately to reflect the document\'s structure.',
            'Return only the converted markdown, with no commentary before or after it.',
        ]);
    }
}
