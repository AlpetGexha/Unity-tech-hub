import { Head, Link } from '@inertiajs/react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import { ClaimList } from '@/components/claim-list';
import { DecisionReport } from '@/components/decision-report';
import { SkillChart } from '@/components/skill-chart';
import { Button } from '@/components/ui/button';
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
}: {
    analysis?: Analysis | null;
}) {
    /**
     * Never fall back to the mock. A missing or renamed prop must look empty,
     * because a page that quietly renders a fabricated candidate is worse than
     * a page that renders nothing.
     */
    if (!analysis?.overall) {
        return (
            <>
                <Head title="Candidate analysis" />

                <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4 md:p-6">
                    <header className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Candidate analysis
                        </h1>
                    </header>

                    <div className="space-y-3 rounded-xl border bg-card p-6 text-card-foreground">
                        <h2 className="font-medium">No analysis yet</h2>

                        <p className="max-w-prose text-sm text-muted-foreground">
                            Nothing has been analysed for this candidate. Run an
                            analysis and the fit score, the skill breakdown and
                            every CV claim checked against real code will appear
                            here.
                        </p>

                        <Button asChild>
                            <Link href={CandidateController.create()}>
                                Go to candidates
                            </Link>
                        </Button>
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
