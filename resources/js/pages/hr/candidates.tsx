import { Head, Link } from '@inertiajs/react';
import { CandidatesTable } from '@/components/candidates-table';
import type { EvaluationOverviewCandidate } from '@/components/candidates-table';
import { Button } from '@/components/ui/button';
import {
    create as candidateCreate,
    index as candidatesIndex,
} from '@/routes/hr/candidates';

export default function Candidates({
    candidates = [],
}: {
    candidates?: EvaluationOverviewCandidate[];
}) {
    return (
        <>
            <Head title="Candidates" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Candidates
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        The full candidate roster, so you can check evaluation
                        status without digging through the dashboard.
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

Candidates.layout = {
    breadcrumbs: [
        {
            title: 'Candidates',
            href: candidatesIndex(),
        },
    ],
};
