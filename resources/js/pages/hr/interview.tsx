import { Head } from '@inertiajs/react';
import { CircleAlert, Mic, Square } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import InterviewController from '@/actions/App/Http/Controllers/InterviewController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { InterviewQuestion } from '@/types/analysis';

type Grade = {
    transcript: string;
    score: number;
    notes: string;
};

type GradedAnswer = Grade & {
    question: string;
};

type Phase =
    'loading' | 'ready' | 'recording' | 'grading' | 'graded' | 'complete';

type PaulaTest = {
    audioUrl: string;
    question: InterviewQuestion;
};

const csrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * These four endpoints return JSON rather than Inertia responses, so they are
 * fetched directly. Inertia's client would treat the reply as a page visit.
 */
const postJson = async <T,>(url: string, body: unknown): Promise<T> => {
    const response = await fetch(url, {
        method: 'post',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status}).`);
    }

    return response.json() as Promise<T>;
};

export default function Interview({
    candidateId,
    paulaTest,
}: {
    candidateId: number;
    paulaTest: PaulaTest;
}) {
    const [index, setIndex] = useState(0);
    const [isPaulaTest, setIsPaulaTest] = useState(true);
    const [question, setQuestion] = useState<InterviewQuestion | null>(
        paulaTest.question,
    );
    const [audioUrl, setAudioUrl] = useState(paulaTest.audioUrl);
    const [grade, setGrade] = useState<Grade | null>(null);
    const [answers, setAnswers] = useState<GradedAnswer[]>([]);
    const [phase, setPhase] = useState<Phase>('loading');
    const [error, setError] = useState('');

    const recorder = useRef<MediaRecorder | null>(null);
    const chunks = useRef<Blob[]>([]);
    const stream = useRef<MediaStream | null>(null);
    const reportSent = useRef(false);

    useEffect(() => {
        let cancelled = false;

        const load = async (): Promise<void> => {
            setPhase('loading');
            setQuestion(null);
            setGrade(null);
            setAudioUrl('');
            setError('');

            if (isPaulaTest) {
                setQuestion(paulaTest.question);
                setAudioUrl(paulaTest.audioUrl);
                setPhase('ready');

                return;
            }

            let loaded: InterviewQuestion;

            try {
                const response = await fetch(
                    InterviewController.question.url(candidateId, {
                        query: { i: index },
                    }),
                    { headers: { Accept: 'application/json' } },
                );

                if (cancelled) {
                    return;
                }

                /**
                 * The question endpoint 404s once the index runs past the last
                 * question, which is how the page learns the interview is over
                 * without being told the count up front. A 404 on the very
                 * first index means there is nothing to ask at all.
                 */
                if (response.status === 404) {
                    if (index === 0) {
                        setError(
                            'This candidate has no interview questions yet. Run an analysis first, then come back.',
                        );
                        setPhase('ready');

                        return;
                    }

                    setPhase('complete');

                    return;
                }

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                loaded = (await response.json()) as InterviewQuestion;
            } catch {
                if (!cancelled) {
                    setError(
                        'We could not load the next question. Check the server is running, then reload.',
                    );
                    setPhase('ready');
                }

                return;
            }

            if (cancelled) {
                return;
            }

            setQuestion(loaded);
            setPhase('ready');

            /**
             * Speech is a nicety, not a dependency. If the provider is down the
             * question is still on screen and still answerable.
             */
            try {
                const spoken = await postJson<{ url: string }>(
                    InterviewController.speak.url(),
                    { text: loaded.question },
                );

                if (!cancelled) {
                    setAudioUrl(spoken.url);
                }
            } catch {
                // Read the question aloud yourself; the loop still works.
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [index, candidateId, isPaulaTest, paulaTest]);

    useEffect(() => {
        if (phase !== 'complete' || reportSent.current) {
            return;
        }

        reportSent.current = true;

        postJson(InterviewController.report.url(candidateId), {
            answers,
        }).catch(() => {
            setError(
                'The interview finished but we could not save the report. The answers below are still on screen.',
            );
        });
    }, [phase, answers, candidateId]);

    const releaseMicrophone = (): void => {
        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;
    };

    useEffect(() => releaseMicrophone, []);

    const submitAnswer = useCallback(
        async (
            recorded: Blob,
            answerQuestion: InterviewQuestion | null = question,
            filename = 'answer.webm',
        ): Promise<void> => {
            if (!answerQuestion) {
                return;
            }

            const payload = new FormData();

            payload.append('audio', recorded, filename);
            payload.append('question', answerQuestion.question);
            answerQuestion.ideal_answer_points.forEach((point) => {
                payload.append('ideal_answer_points[]', point);
            });

            try {
                const response = await fetch(
                    InterviewController.answer.url(candidateId),
                    {
                        method: 'post',
                        headers: {
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': csrfToken(),
                        },
                        body: payload,
                    },
                );

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                setGrade((await response.json()) as Grade);
                setPhase('graded');
            } catch {
                setError(
                    'We could not transcribe that answer. Record it again, or run the Paula real-API test.',
                );
                setPhase('ready');
            }
        },
        [candidateId, question],
    );

    const runPaulaTest = useCallback(async (): Promise<void> => {
        setError('');
        setGrade(null);
        setQuestion(paulaTest.question);
        setAudioUrl(paulaTest.audioUrl);
        setIsPaulaTest(true);
        setPhase('grading');

        try {
            const response = await fetch(paulaTest.audioUrl);

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            await submitAnswer(
                await response.blob(),
                paulaTest.question,
                'paula-interview.mp3',
            );
        } catch {
            setError(
                'The Paula recording could not be transcribed or graded. Check OPENAI_API_KEY and DEMO_FAKE_AI=false, then try again.',
            );
            setPhase('ready');
        }
    }, [paulaTest, submitAnswer]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent): void => {
            const target = event.target as HTMLElement | null;

            if (
                target &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.isContentEditable)
            ) {
                return;
            }

            if (!event.shiftKey || event.key.toLowerCase() !== 'f') {
                return;
            }

            event.preventDefault();
            void runPaulaTest();
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [runPaulaTest]);

    const startRecording = async (): Promise<void> => {
        setError('');

        if (!navigator.mediaDevices?.getUserMedia) {
            setError(
                'This browser will not hand over the microphone. Use Chrome or Safari, over localhost or https.',
            );

            return;
        }

        try {
            const media = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });

            stream.current = media;
            chunks.current = [];

            const active = new MediaRecorder(media);

            active.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    chunks.current.push(event.data);
                }
            };

            active.onstop = () => {
                releaseMicrophone();
                void submitAnswer(
                    new Blob(chunks.current, { type: active.mimeType }),
                );
            };

            active.start();
            recorder.current = active;
            setPhase('recording');
        } catch {
            setError(
                'We could not reach the microphone. Give the browser permission and try again, or run the Paula real-API test.',
            );
        }
    };

    const stopRecording = (): void => {
        setPhase('grading');
        recorder.current?.stop();
        recorder.current = null;
    };

    const nextQuestion = (): void => {
        if (grade && question) {
            setAnswers((previous) => [
                ...previous,
                { ...grade, question: question.question },
            ]);
        }

        if (isPaulaTest) {
            setIsPaulaTest(false);
            setGrade(null);
            setAudioUrl('');

            return;
        }

        setIndex((previous) => previous + 1);
    };

    const insecure = typeof window !== 'undefined' && !window.isSecureContext;

    if (phase === 'complete') {
        const total = answers.reduce((sum, answer) => sum + answer.score, 0);
        const average =
            answers.length === 0 ? 0 : Math.round(total / answers.length);

        return (
            <>
                <Head title="Interview complete" />

                <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                    <header className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Interview complete
                        </h1>

                        <p className="text-sm text-muted-foreground">
                            {answers.length} answer
                            {answers.length === 1 ? '' : 's'} recorded,
                            averaging {average} out of 100.
                        </p>
                    </header>

                    {error !== '' && <ErrorNote message={error} />}

                    <ol className="space-y-3">
                        {answers.map((answer, position) => (
                            <li
                                key={`${answer.question}-${position}`}
                                className="space-y-2 rounded-xl border bg-card p-5 text-card-foreground"
                            >
                                <p className="leading-snug font-medium">
                                    {answer.question}
                                </p>

                                <p className="text-sm text-muted-foreground tabular-nums">
                                    Scored {answer.score} out of 100
                                </p>

                                <blockquote className="border-l-2 pl-3 text-sm text-muted-foreground italic">
                                    &ldquo;{answer.transcript}&rdquo;
                                </blockquote>

                                <p className="text-sm">{answer.notes}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Interview" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Interview
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Question {index + 1}. Play the question, record the
                        answer, and it is transcribed and graded against what a
                        good answer should cover.
                    </p>
                </header>

                {insecure && (
                    <ErrorNote message="This page is not on a secure origin, so the browser will refuse the microphone. Open it on localhost or over https." />
                )}

                {error !== '' && <ErrorNote message={error} />}

                <section
                    aria-labelledby="question-heading"
                    className="space-y-4 rounded-xl border bg-card p-5 text-card-foreground md:p-6"
                >
                    <h2 id="question-heading" className="sr-only">
                        Current question
                    </h2>

                    {phase === 'loading' && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner />
                            Loading the question
                        </p>
                    )}

                    {question && (
                        <>
                            <p className="text-lg leading-snug font-medium text-pretty">
                                {question.question}
                            </p>

                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                <span>Tests {question.targets_skill}</span>

                                <span aria-hidden="true">&middot;</span>

                                {question.grounded_in === 'job_requirement' ? (
                                    <span>
                                        Grounded in the role requirements
                                    </span>
                                ) : (
                                    <span>
                                        Grounded in{' '}
                                        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.7rem] break-all text-foreground">
                                            {question.grounded_in}
                                        </code>
                                    </span>
                                )}
                            </p>
                        </>
                    )}

                    {audioUrl !== '' && (
                        <audio
                            controls
                            src={audioUrl}
                            className="w-full"
                            aria-label={
                                isPaulaTest
                                    ? 'Paula question and answer test recording'
                                    : 'The question read aloud'
                            }
                        >
                            Your browser cannot play audio. Read the question
                            above instead.
                        </audio>
                    )}

                    <div className="flex flex-wrap items-center gap-3 pt-1">
                        {isPaulaTest && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void runPaulaTest()}
                                disabled={
                                    phase === 'loading' || phase === 'grading'
                                }
                            >
                                Test Paula recording with real API
                            </Button>
                        )}

                        {phase === 'recording' ? (
                            <Button type="button" onClick={stopRecording}>
                                <Square aria-hidden="true" />
                                Stop recording
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={() => void startRecording()}
                                disabled={
                                    phase === 'loading' ||
                                    phase === 'grading' ||
                                    question === null
                                }
                            >
                                <Mic aria-hidden="true" />
                                {phase === 'graded'
                                    ? 'Record again'
                                    : 'Start recording'}
                            </Button>
                        )}

                        {phase === 'recording' && (
                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                <span
                                    aria-hidden="true"
                                    className="size-2 rounded-full bg-rose-600 motion-safe:animate-pulse dark:bg-rose-400"
                                />
                                Recording
                            </span>
                        )}

                        {phase === 'grading' && (
                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Transcribing and grading
                            </span>
                        )}
                    </div>
                </section>

                {grade && (
                    <section
                        aria-labelledby="grade-heading"
                        className="space-y-4 rounded-xl border bg-card p-5 text-card-foreground md:p-6"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-3">
                            <h2
                                id="grade-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                How that answer scored
                            </h2>

                            <p className="flex items-baseline gap-1">
                                <span className="text-3xl font-semibold tabular-nums">
                                    {grade.score}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    / 100
                                </span>
                            </p>
                        </div>

                        <blockquote className="border-l-2 pl-3">
                            <p className="text-sm text-muted-foreground italic">
                                &ldquo;{grade.transcript}&rdquo;
                            </p>

                            <footer className="mt-1.5 text-xs text-muted-foreground">
                                What we heard
                            </footer>
                        </blockquote>

                        <p className="leading-relaxed text-pretty">
                            {grade.notes}
                        </p>

                        <Button type="button" onClick={nextQuestion}>
                            Next question
                        </Button>
                    </section>
                )}
            </div>
        </>
    );
}

function ErrorNote({ message }: { message: string }) {
    return (
        <p
            role="status"
            className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            <CircleAlert
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            {message}
        </p>
    );
}

Interview.layout = {
    breadcrumbs: [
        {
            title: 'Interview',
            href: '/hr',
        },
    ],
};
