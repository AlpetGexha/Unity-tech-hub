import { Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import { ClaimList } from '@/components/claim-list';
import { DecisionReport } from '@/components/decision-report';
import { SkillChart } from '@/components/skill-chart';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { Analysis, Recommendation } from '@/types/analysis';

const recommendationLabels: Record<Recommendation, string> = {
    strong_yes: 'Strong yes',
    yes: 'Yes',
    maybe: 'Maybe',
    no: 'No',
};

/**
 * Colour on this page belongs to claim verification alone, so the
 * recommendation uses weight rather than hue to show how strong it is.
 */
const recommendationStyles: Record<Recommendation, string> = {
    strong_yes: 'bg-primary text-primary-foreground',
    yes: 'bg-primary text-primary-foreground',
    maybe: 'bg-secondary text-secondary-foreground',
    no: 'bg-secondary text-secondary-foreground',
};

const analysedOn = (generatedAt: string): string => {
    const date = new Date(generatedAt);

    if (Number.isNaN(date.getTime())) {
        return 'an unknown date';
    }

    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(date);
};

export default function CandidateAnalysis({
    analysis,
    evaluationStatus = null,
}: {
    analysis?: Analysis | null;
    evaluationStatus?: 'pending' | 'running' | 'completed' | 'failed' | null;
}) {
    const isEvaluating =
        !analysis?.overall &&
        (evaluationStatus === 'pending' || evaluationStatus === 'running');
    const { stop } = usePoll(
        2000,
        {
            only: ['analysis', 'evaluationStatus'],
        },
        {
            mode: 'rest',
            autoStart: isEvaluating,
        },
    );

    useEffect(() => {
        if (!isEvaluating) {
            stop();
        }
    }, [isEvaluating, stop]);

    /**
     * Never fall back to the mock. A missing or renamed prop must look empty,
     * because a page that quietly renders a fabricated candidate is worse than
     * a page that renders nothing.
     */
    if (!analysis?.overall) {
        const isQueued = evaluationStatus === 'pending';
        const waitingTitle = isQueued
            ? 'Waiting for the evaluation queue'
            : 'AI evaluation in progress';
        const waitingMessage = isQueued
            ? 'Your candidate was saved successfully and is waiting for an available queue worker.'
            : 'The AI is reviewing the CV against the role and public GitHub evidence.';

        return (
            <>
                <Head title="Candidate analysis" />

                <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4 md:p-6">
                    <header className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Candidate analysis
                        </h1>
                    </header>

                    <div
                        aria-live="polite"
                        className="space-y-6 rounded-xl border bg-card p-6 text-card-foreground md:p-8"
                    >
                        {isEvaluating && (
                            <div className="flex flex-col items-center gap-5 text-center">
                                <div className="relative flex size-20 items-center justify-center rounded-full bg-primary/10">
                                    <div className="absolute inset-0 animate-ping rounded-full bg-primary/10" />
                                    <Spinner className="relative size-9 text-primary" />
                                </div>

                                <div className="space-y-2">
                                    <h2 className="text-lg font-semibold">
                                        {waitingTitle}
                                    </h2>

                                    <p className="mx-auto max-w-xl text-sm leading-relaxed text-muted-foreground">
                                        {waitingMessage} This page checks for
                                        the result automatically and will show
                                        the full evaluation as soon as the job
                                        finishes.
                                    </p>
                                </div>

                                <div className="w-full max-w-xl space-y-3">
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div className="h-full w-1/3 animate-pulse rounded-full bg-primary" />
                                    </div>

                                    <div className="flex flex-wrap justify-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Candidate saved
                                        </span>
                                        <span>
                                            {isQueued
                                                ? 'Queued for AI evaluation'
                                                : 'AI is checking evidence'}
                                        </span>
                                        <span>
                                            Result appears automatically
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {evaluationStatus === 'failed' && (
                            <h2 className="font-medium">Evaluation failed</h2>
                        )}

                        {!isEvaluating && evaluationStatus !== 'failed' && (
                            <h2 className="font-medium">No analysis yet</h2>
                        )}

                        {evaluationStatus === 'failed' && (
                            <p className="max-w-prose text-sm text-muted-foreground">
                                The AI evaluation could not be completed. Please
                                retry the evaluation from the candidate
                                workflow.
                            </p>
                        )}

                        {!isEvaluating && evaluationStatus !== 'failed' && (
                            <p className="max-w-prose text-sm text-muted-foreground">
                                Nothing has been analysed for this candidate
                                yet. The fit score, skill breakdown and every CV
                                claim checked against real code will appear
                                here.
                            </p>
                        )}

                        {!isEvaluating && (
                            <Button asChild>
                                <Link href={CandidateController.create()}>
                                    Go to candidates
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </>
        );
    }

    /**
     * `analyses.result` is a JSON blob, so a row written by an older shape can
     * satisfy the type at compile time and still be missing keys at runtime.
     * Fill the collection-shaped keys here, once, rather than guarding each of
     * the readers downstream.
     */
    const overall = analysis.overall;
    const skills = analysis.skills ?? [];
    const claims = analysis.claims ?? [];
    const rubric = analysis.rubric ?? { criteria: [], scale: '' };
    const safeAnalysis: Analysis = {
        ...analysis,
        skills,
        claims,
        rubric,
        interview_questions: analysis.interview_questions ?? [],
    };

    return (
        <>
            <Head title="Candidate analysis" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Candidate analysis
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Candidate {analysis.candidate_id} against role{' '}
                        {analysis.job_id}, analysed{' '}
                        {analysedOn(analysis.generated_at)}.
                    </p>
                </header>

                <section
                    aria-labelledby="cv-summary-heading"
                    className="space-y-3 rounded-xl border bg-card p-5 text-card-foreground md:p-6"
                >
                    <h2
                        id="cv-summary-heading"
                        className="text-lg font-semibold tracking-tight"
                    >
                        CV summary
                    </h2>

                    <p className="leading-relaxed text-pretty text-muted-foreground">
                        {analysis.cv_summary ??
                            'No CV summary was saved for this analysis.'}
                    </p>
                </section>

                <section
                    aria-labelledby="verdict-heading"
                    className="rounded-xl border bg-card p-5 text-card-foreground md:p-6"
                >
                    <h2 id="verdict-heading" className="sr-only">
                        Overall verdict
                    </h2>

                    <div className="flex flex-col gap-6 md:flex-row md:items-start md:gap-8">
                        <div className="shrink-0 md:w-48">
                            <p className="text-sm text-muted-foreground">
                                Fit score
                            </p>

                            <p className="mt-1 flex items-baseline gap-1">
                                <span className="text-5xl font-semibold tabular-nums">
                                    {overall.fit_score}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    / 100
                                </span>
                            </p>

                            <div
                                aria-hidden="true"
                                className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-foreground"
                                    style={{
                                        width: `${Math.min(Math.max(overall.fit_score, 0), 100)}%`,
                                    }}
                                />
                            </div>

                            <p className="mt-3">
                                <span
                                    className={`inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium ${recommendationStyles[overall.recommendation]}`}
                                >
                                    {recommendationLabels[
                                        overall.recommendation
                                    ] ?? overall.recommendation}
                                </span>
                            </p>
                        </div>

                        <div className="min-w-0 flex-1 space-y-3">
                            <p className="leading-relaxed text-pretty">
                                {overall.summary}
                            </p>

                            {rubric.scale !== '' && (
                                <p className="text-xs text-muted-foreground">
                                    Scored on the role&rsquo;s rubric (
                                    {rubric.scale}
                                    ).
                                </p>
                            )}
                        </div>
                    </div>
                </section>

                <section aria-labelledby="claims-heading" className="space-y-4">
                    <div className="space-y-1">
                        <h2
                            id="claims-heading"
                            className="text-lg font-semibold tracking-tight"
                        >
                            What the CV claims, and what the code shows
                        </h2>

                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Each claim was checked against the candidate&rsquo;s
                            CV and public GitHub. The quote underneath is the
                            evidence we found, and where we found it.
                        </p>
                    </div>

                    <ClaimList claims={claims} />
                </section>

                <section aria-labelledby="skills-heading" className="space-y-4">
                    <div className="space-y-1">
                        <h2
                            id="skills-heading"
                            className="text-lg font-semibold tracking-tight"
                        >
                            Skills against the rubric
                        </h2>

                        <p className="max-w-2xl text-sm text-muted-foreground">
                            How the candidate scored on each criterion this role
                            asks for, and why.
                        </p>
                    </div>

                    <div className="grid gap-6 rounded-xl border bg-card p-5 text-card-foreground md:p-6 lg:grid-cols-2 lg:gap-8">
                        <SkillChart skills={skills} />

                        <dl className="space-y-4">
                            {skills.map((skill) => (
                                <div key={skill.name} className="space-y-1">
                                    <dt className="flex flex-wrap items-baseline gap-x-2 text-sm font-medium">
                                        {skill.name}
                                        <span className="text-xs font-normal text-muted-foreground tabular-nums">
                                            {skill.score}/100 &middot;{' '}
                                            {skill.weight}% of the rubric
                                        </span>
                                    </dt>

                                    <dd className="text-sm leading-relaxed text-pretty text-muted-foreground">
                                        {skill.reasoning}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </section>

                <DecisionReport analysis={safeAnalysis} />
            </div>
        </>
    );
}

CandidateAnalysis.layout = {
    breadcrumbs: [
        {
            title: 'Candidate analysis',
            href: '/hr',
        },
    ],
};
