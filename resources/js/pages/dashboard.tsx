import { Head, Link } from '@inertiajs/react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create as candidateCreate } from '@/routes/hr/candidates';
import type { Recommendation } from '@/types/analysis';

const recommendationLabels: Record<Recommendation, string> = {
    strong_yes: 'Strong yes',
    yes: 'Yes',
    maybe: 'Maybe',
    no: 'No',
};

/**
 * Matches the weight-not-hue convention from hr/dashboard.tsx, since this is
 * the same `overall.recommendation` field shown at list level here.
 */
const recommendationStyles: Record<Recommendation, string> = {
    strong_yes: 'bg-primary text-primary-foreground',
    yes: 'bg-primary text-primary-foreground',
    maybe: 'bg-secondary text-secondary-foreground',
    no: 'bg-secondary text-secondary-foreground',
};

type DashboardCandidate = {
    id: number;
    name: string;
    job_title: string | null;
    fit_score: number | null;
    recommendation: Recommendation | null;
    analyzed_at: string | null;
};

export default function Dashboard({
    candidates = [],
}: {
    candidates?: DashboardCandidate[];
}) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Candidates
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Every candidate added so far, and how they scored
                        against the role they applied for.
                    </p>
                </header>

                {candidates.length === 0 ? (
                    <div className="space-y-3 rounded-xl border bg-card p-6 text-card-foreground">
                        <h2 className="font-medium">No candidates yet</h2>

                        <p className="max-w-prose text-sm text-muted-foreground">
                            Add your first candidate to start scoring them
                            against a role's requirements.
                        </p>

                        <Button asChild>
                            <Link href={candidateCreate()}>
                                Add a candidate
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card text-card-foreground">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Candidate
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Fit score
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Recommendation
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        <span className="sr-only">
                                            Evaluation
                                        </span>
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {candidates.map((candidate) => (
                                    <tr key={candidate.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {candidate.name}
                                        </td>

                                        <td className="px-4 py-3 text-muted-foreground">
                                            {candidate.job_title ?? '—'}
                                        </td>

                                        <td className="px-4 py-3 tabular-nums">
                                            {candidate.fit_score ?? '—'}
                                        </td>

                                        <td className="px-4 py-3">
                                            {candidate.recommendation ? (
                                                <span
                                                    className={`inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ${recommendationStyles[candidate.recommendation]}`}
                                                >
                                                    {recommendationLabels[
                                                        candidate
                                                            .recommendation
                                                    ] ??
                                                        candidate.recommendation}
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                                    Not yet evaluated
                                                </span>
                                            )}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={CandidateController.show(
                                                    candidate.id,
                                                )}
                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
