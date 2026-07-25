import { ChevronRight, Download } from 'lucide-react';
import { ClaimList } from '@/components/claim-list';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Analysis, ClaimStatus } from '@/types/analysis';

const statusWords: Record<ClaimStatus, string> = {
    verified: 'VERIFIED',
    weak: 'WEAK EVIDENCE',
    unsupported: 'NOT SUPPORTED',
};

const rule = (heading: string): string =>
    `${heading}\n${'-'.repeat(heading.length)}`;

/** `grounded_in` is either a file and line range, or the sentinel below. */
const groundedInLabel = (groundedIn: string): string =>
    groundedIn === 'job_requirement' ? 'the role requirements' : groundedIn;

/**
 * A hiring file has to be readable by a person years later, so the text export
 * carries the same evidence the screen shows rather than a score summary.
 */
const toPlainText = (analysis: Analysis): string => {
    const lines: string[] = [
        'HrHire decision record',
        '======================',
        '',
        `Candidate: ${analysis.candidate_id}`,
        `Role: ${analysis.job_id}`,
        `Analysed: ${analysis.generated_at}`,
        '',
        rule('RECOMMENDATION'),
        `${analysis.overall.recommendation} (fit score ${analysis.overall.fit_score}/100)`,
        '',
        analysis.overall.summary,
        '',
        rule('RUBRIC'),
        `Scale: ${analysis.rubric.scale}`,
        '',
    ];

    analysis.rubric.criteria.forEach((criterion) => {
        lines.push(
            `- ${criterion.name} (${criterion.weight}%)`,
            `  ${criterion.description}`,
        );
    });

    lines.push('', rule('SKILLS'), '');

    analysis.skills.forEach((skill) => {
        lines.push(
            `- ${skill.name}: ${skill.score}/100, ${skill.weight}% of the rubric`,
            `  ${skill.reasoning}`,
        );
    });

    lines.push('', rule('CLAIMS CHECKED'), '');

    analysis.claims.forEach((claim) => {
        lines.push(`[${statusWords[claim.status]}] ${claim.claim}`);
        lines.push(`  Evidence: "${claim.evidence}"`);
        lines.push(
            `  Source: ${claim.source}${claim.source_ref === '' ? '' : ` - ${claim.source_ref}`}`,
            '',
        );
    });

    if (analysis.interview_questions.length > 0) {
        lines.push(rule('INTERVIEW QUESTIONS'), '');

        analysis.interview_questions.forEach((question, index) => {
            lines.push(
                `${index + 1}. ${question.question}`,
                `   Tests: ${question.targets_skill}`,
                `   Grounded in: ${groundedInLabel(question.grounded_in)}`,
                '   A good answer covers:',
            );

            question.ideal_answer_points.forEach((point) => {
                lines.push(`     - ${point}`);
            });

            lines.push('');
        });
    }

    lines.push(rule('FAIRNESS'), analysis.fairness_note, '');

    return lines.join('\n');
};

const triggerDownload = (
    contents: string,
    filename: string,
    type: string,
): void => {
    const url = URL.createObjectURL(new Blob([contents], { type }));
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

export function DecisionReport({
    analysis,
    className,
}: {
    analysis: Analysis;
    className?: string;
}) {
    const basename = `decision-record-candidate-${analysis.candidate_id}-role-${analysis.job_id}`;

    return (
        <section
            aria-labelledby="decision-record-heading"
            className={cn(
                'space-y-4 rounded-xl border bg-card p-5 text-card-foreground md:p-6',
                className,
            )}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <h2
                        id="decision-record-heading"
                        className="text-lg font-semibold tracking-tight"
                    >
                        Decision record
                    </h2>

                    <p className="max-w-xl text-sm text-muted-foreground">
                        Everything this recommendation rests on, in one file.
                        Download it for the hiring file so the reasoning is
                        still there when someone asks.
                    </p>
                </div>

                <div className="flex shrink-0 flex-wrap gap-2">
                    <Button
                        type="button"
                        onClick={() =>
                            triggerDownload(
                                toPlainText(analysis),
                                `${basename}.txt`,
                                'text/plain;charset=utf-8',
                            )
                        }
                    >
                        <Download aria-hidden="true" />
                        Download as text
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            triggerDownload(
                                JSON.stringify(analysis, null, 2),
                                `${basename}.json`,
                                'application/json',
                            )
                        }
                    >
                        JSON
                    </Button>
                </div>
            </div>

            <details className="group rounded-lg border">
                <summary className="flex cursor-pointer items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium outline-none select-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">
                    <ChevronRight
                        aria-hidden="true"
                        className="size-4 shrink-0 transition-transform group-open:rotate-90 motion-reduce:transition-none"
                    />
                    Show the full record
                </summary>

                <div className="space-y-8 border-t px-4 py-5">
                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Rubric this role is scored on
                        </h3>

                        <p className="text-xs text-muted-foreground">
                            Scale: {analysis.rubric.scale}
                        </p>

                        <dl className="space-y-3">
                            {analysis.rubric.criteria.map((criterion) => (
                                <div key={criterion.name}>
                                    <dt className="text-sm font-medium">
                                        {criterion.name}{' '}
                                        <span className="font-normal text-muted-foreground tabular-nums">
                                            &middot; {criterion.weight}%
                                        </span>
                                    </dt>

                                    <dd className="text-sm text-muted-foreground">
                                        {criterion.description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">Skill scores</h3>

                        <dl className="space-y-3">
                            {analysis.skills.map((skill) => (
                                <div key={skill.name}>
                                    <dt className="text-sm font-medium">
                                        {skill.name}{' '}
                                        <span className="font-normal text-muted-foreground tabular-nums">
                                            &middot; {skill.score}/100 &middot;{' '}
                                            {skill.weight}% of the rubric
                                        </span>
                                    </dt>

                                    <dd className="text-sm text-muted-foreground">
                                        {skill.reasoning}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Claims and the evidence behind them
                        </h3>

                        <ClaimList claims={analysis.claims} />
                    </div>

                    {analysis.interview_questions.length > 0 && (
                        <div className="space-y-3">
                            <h3 className="text-sm font-semibold">
                                Questions to ask, and where they come from
                            </h3>

                            <ol className="space-y-3">
                                {analysis.interview_questions.map(
                                    (question, index) => (
                                        <li
                                            key={`${question.question}-${index}`}
                                            className="space-y-2 rounded-lg border p-4"
                                        >
                                            <p className="leading-snug font-medium">
                                                {question.question}
                                            </p>

                                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                <span>
                                                    Tests{' '}
                                                    {question.targets_skill}
                                                </span>

                                                <span aria-hidden="true">
                                                    &middot;
                                                </span>

                                                {question.grounded_in ===
                                                'job_requirement' ? (
                                                    <span>
                                                        Grounded in the role
                                                        requirements
                                                    </span>
                                                ) : (
                                                    <span>
                                                        Grounded in{' '}
                                                        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.7rem] break-all text-foreground">
                                                            {
                                                                question.grounded_in
                                                            }
                                                        </code>
                                                    </span>
                                                )}
                                            </p>

                                            {question.ideal_answer_points
                                                .length > 0 && (
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium">
                                                        A good answer covers
                                                    </p>

                                                    <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                                        {question.ideal_answer_points.map(
                                                            (
                                                                point,
                                                                pointIndex,
                                                            ) => (
                                                                <li
                                                                    key={`${point}-${pointIndex}`}
                                                                >
                                                                    {point}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </div>
                                            )}
                                        </li>
                                    ),
                                )}
                            </ol>
                        </div>
                    )}
                </div>
            </details>

            <p className="text-xs text-muted-foreground">
                {analysis.fairness_note}
            </p>
        </section>
    );
}
