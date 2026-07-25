<?php

namespace App\Http\Controllers;

use App\Ai\Agents\InterviewGrader;
use App\Models\Candidate;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;

class InterviewController extends Controller
{
    /**
     * Generate speech for the given text and return its public URL.
     */
    public function speak(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $path = Audio::of($validated['text'])->generate()->storePublicly('interviews', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Return one interview question from a candidate's latest analysis.
     */
    public function question(Request $request, Candidate $candidate): JsonResponse
    {
        $index = $request->integer('i');

        $analysis = $candidate->analyses()->latest('id')->first();

        abort_if($analysis === null, 404, 'This candidate has not been analysed yet.');

        $questions = $analysis->result['interview_questions'] ?? [];

        abort_if(! array_key_exists($index, $questions), 404, 'No interview question at that index.');

        return response()->json($questions[$index]);
    }

    /**
     * Transcribe a recorded answer and grade it against the question's ideal answer points.
     */
    public function answer(Request $request, Candidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file'],
            'question' => ['required', 'string'],
            'ideal_answer_points' => ['required', 'array'],
            'ideal_answer_points.*' => ['string'],
        ]);

        $transcript = (string) Transcription::fromUpload($request->file('audio'))->generate();

        $graded = (new InterviewGrader($validated['question'], $validated['ideal_answer_points']))
            ->prompt($transcript);

        return response()->json([
            'transcript' => $transcript,
            'score' => $graded['score'],
            'notes' => $graded['notes'],
        ]);
    }

    /**
     * Store the completed interview's per-question results for a candidate.
     */
    public function report(Request $request, Candidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question' => ['required', 'string'],
            'answers.*.transcript' => ['required', 'string'],
            'answers.*.score' => ['required', 'integer', 'min:0', 'max:100'],
            'answers.*.notes' => ['required', 'string'],
        ]);

        $interview = Interview::create([
            'candidate_id' => $candidate->id,
            'result' => $validated['answers'],
        ]);

        return response()->json($interview->result);
    }
}
