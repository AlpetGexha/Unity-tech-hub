import { Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type FailedRow = {
    row: number;
    errors: string[];
};

type CandidateImport = {
    id: number;
    original_filename: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    failed_rows: FailedRow[];
};

type BatchProgress = {
    total_jobs: number;
    pending_jobs: number;
    failed_jobs: number;
    processed_jobs: number;
    progress: number;
    finished: boolean;
    cancelled: boolean;
};

export default function CandidateImportProgress({
    import: candidateImport,
    batch,
}: {
    import: CandidateImport;
    batch: BatchProgress;
}) {
    const { stop } = usePoll(
        2000,
        {
            only: ['import', 'batch'],
        },
        {
            mode: 'rest',
        },
    );

    useEffect(() => {
        if (batch.finished || batch.cancelled) {
            stop();
        }
    }, [batch.cancelled, batch.finished, stop]);

    const isFinished = batch.finished || batch.cancelled;

    return (
        <>
            <Head title="CSV candidate evaluation" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        CSV candidate evaluation
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        {candidateImport.original_filename}
                    </p>
                </header>

                <section className="space-y-5 rounded-xl border bg-card p-6 text-card-foreground">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="font-medium">
                                {isFinished
                                    ? candidateImport.status === 'failed'
                                        ? 'Finished with failures'
                                        : 'Evaluation complete'
                                    : 'Evaluation in progress'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {batch.processed_jobs} of {batch.total_jobs}{' '}
                                queue jobs finished
                            </p>
                        </div>

                        {!isFinished && <Spinner />}
                    </div>

                    <progress
                        value={batch.progress}
                        max="100"
                        className="h-3 w-full accent-primary"
                    >
                        {batch.progress}%
                    </progress>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border p-3">
                            <p className="text-2xl font-semibold">
                                {candidateImport.valid_rows}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Valid candidates
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <p className="text-2xl font-semibold">
                                {candidateImport.invalid_rows}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Invalid rows
                            </p>
                        </div>
                        <div className="rounded-lg border p-3">
                            <p className="text-2xl font-semibold">
                                {batch.failed_jobs}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Failed evaluations
                            </p>
                        </div>
                    </div>
                </section>

                {candidateImport.failed_rows.length > 0 && (
                    <section className="space-y-3 rounded-xl border border-destructive/30 bg-destructive/5 p-6">
                        <h2 className="font-medium">Rows skipped during import</h2>

                        <ul className="space-y-2 text-sm text-muted-foreground">
                            {candidateImport.failed_rows.map((failedRow) => (
                                <li key={failedRow.row}>
                                    <span className="font-medium text-foreground">
                                        Row {failedRow.row}:
                                    </span>{' '}
                                    {failedRow.errors.join(' ')}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <Button asChild variant="outline">
                    <Link href={CandidateController.create()}>
                        Back to New Candidate
                    </Link>
                </Button>
            </div>
        </>
    );
}

CandidateImportProgress.layout = {
    breadcrumbs: [
        {
            title: 'CSV candidate evaluation',
            href: CandidateController.create(),
        },
    ],
};
