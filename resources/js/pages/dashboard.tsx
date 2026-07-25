import { Head, Link } from '@inertiajs/react';
import { CandidatesTable } from '@/components/candidates-table';
import type { EvaluationOverviewCandidate } from '@/components/candidates-table';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create as candidateCreate } from '@/routes/hr/candidates';

export default function Dashboard({
    candidates = [],
}: {
    candidates?: EvaluationOverviewCandidate[];
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
                    <CandidatesTable candidates={candidates} />
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
