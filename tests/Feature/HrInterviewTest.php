<?php

namespace Tests\Feature;

use App\Ai\Agents\InterviewGrader;
use App\Models\Analysis;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;
use Tests\TestCase;

class HrInterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interview_page_renders_for_a_candidate(): void
    {
        $candidate = $this->candidateWithAnalysis();

        $this->get('/hr/candidates/'.$candidate->id.'/interview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('hr/interview')
                ->where('candidateId', $candidate->id)
            );
    }

    public function test_speak_generates_audio_and_returns_a_public_url(): void
    {
        Storage::fake('public');
        Audio::fake();

        $this->post('/hr/interview/speak', ['text' => 'Walk me through your testing strategy.'])
            ->assertOk()
            ->assertJsonStructure(['url']);

        Audio::assertGenerated(fn (): bool => true);
    }

    public function test_question_returns_the_indexed_interview_question(): void
    {
        $candidate = $this->candidateWithAnalysis();

        $this->get('/hr/candidates/'.$candidate->id.'/interview/question?i=0')
            ->assertOk()
            ->assertJsonPath('question', 'Walk me through the ledger posting model.')
            ->assertJsonPath('targets_skill', 'Laravel');
    }

    public function test_question_404s_past_the_end_of_the_list(): void
    {
        $candidate = $this->candidateWithAnalysis();

        $this->get('/hr/candidates/'.$candidate->id.'/interview/question?i=5')
            ->assertNotFound();
    }

    public function test_question_404s_before_any_analysis_exists(): void
    {
        $candidate = Candidate::factory()->create();

        $this->get('/hr/candidates/'.$candidate->id.'/interview/question?i=0')
            ->assertNotFound();
    }

    public function test_answer_transcribes_and_grades_the_recorded_response(): void
    {
        $candidate = $this->candidateWithAnalysis();

        Transcription::fake(fn (): string => 'I would add more workers and prioritise the queue.');
        InterviewGrader::fake(fn (): array => ['score' => 82, 'notes' => 'Solid, specific answer.']);

        $this->post('/hr/candidates/'.$candidate->id.'/interview/answer', [
            'audio' => UploadedFile::fake()->createWithContent('answer.wav', str_repeat('x', 128)),
            'question' => 'Walk me through the ledger posting model.',
            'ideal_answer_points' => ['Double-entry invariants'],
        ])
            ->assertOk()
            ->assertJson([
                'transcript' => 'I would add more workers and prioritise the queue.',
                'score' => 82,
                'notes' => 'Solid, specific answer.',
            ]);
    }

    public function test_report_stores_the_completed_interview(): void
    {
        $candidate = $this->candidateWithAnalysis();

        $this->post('/hr/candidates/'.$candidate->id.'/interview/report', [
            'answers' => [
                [
                    'question' => 'Walk me through the ledger posting model.',
                    'transcript' => 'I would add more workers and prioritise the queue.',
                    'score' => 82,
                    'notes' => 'Solid, specific answer.',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('interviews', 1);
        $this->assertSame($candidate->id, Interview::firstOrFail()->candidate_id);
    }

    /**
     * A candidate whose latest analysis carries one known interview question.
     */
    protected function candidateWithAnalysis(): Candidate
    {
        $candidate = Candidate::factory()->create([
            'job_id' => JobOpening::factory()->create(),
        ]);

        Analysis::create([
            'candidate_id' => $candidate->id,
            'job_id' => $candidate->job_id,
            'input_hash' => 'test-hash',
            'result' => [
                'interview_questions' => [[
                    'question' => 'Walk me through the ledger posting model.',
                    'targets_skill' => 'Laravel',
                    'grounded_in' => 'jamierivera/ledger/src/Posting.php:L1-L1',
                    'ideal_answer_points' => ['Double-entry invariants'],
                ]],
            ],
        ]);

        return $candidate;
    }
}
