import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AnalysisClaim, ClaimSource, ClaimStatus } from '@/types/analysis';

type StatusStyle = {
    label: string;
    icon: LucideIcon;
    /** Left-hand rail on a claim row */
    rail: string;
    /** Badge, and the counter of the same status in the summary row */
    chip: string;
};

/**
 * Status is never signalled by colour alone: every badge carries its own icon
 * and its own word, so the list still reads in greyscale.
 */
const statusStyles: Record<ClaimStatus, StatusStyle> = {
    verified: {
        label: 'Verified',
        icon: CircleCheck,
        rail: 'bg-emerald-600 dark:bg-emerald-400',
        chip: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
    weak: {
        label: 'Weak evidence',
        icon: CircleAlert,
        rail: 'bg-amber-500 dark:bg-amber-400',
        chip: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    unsupported: {
        label: 'Not supported',
        icon: CircleX,
        rail: 'bg-rose-600 dark:bg-rose-400',
        chip: 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
    },
};

const statusOrder: ClaimStatus[] = ['verified', 'weak', 'unsupported'];

const sourceLabels: Record<ClaimSource, string> = {
    cv: 'From the CV',
    github_profile: 'From the GitHub profile',
    repo: 'From a repository',
    source_file: 'From a source file',
};

function StatusBadge({
    status,
    className,
}: {
    status: ClaimStatus;
    className?: string;
}) {
    const { label, icon: Icon, chip } = statusStyles[status];

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium',
                chip,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5" />
            {label}
        </span>
    );
}

function ClaimRow({ claim }: { claim: AnalysisClaim }) {
    const { rail } = statusStyles[claim.status];

    return (
        <li className="relative overflow-hidden rounded-lg border bg-card text-card-foreground">
            <span
                aria-hidden="true"
                className={cn('absolute inset-y-0 left-0 w-1', rail)}
            />

            <div className="space-y-3 py-4 pr-4 pl-5">
                <div className="flex flex-wrap-reverse items-start justify-between gap-x-4 gap-y-2">
                    <p className="min-w-0 flex-1 basis-64 leading-snug font-medium">
                        {claim.claim}
                    </p>

                    <StatusBadge status={claim.status} />
                </div>

                <blockquote className="border-l-2 pl-3 text-sm">
                    <p className="text-muted-foreground italic">
                        &ldquo;{claim.evidence}&rdquo;
                    </p>

                    <footer className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground not-italic">
                        <span>{sourceLabels[claim.source]}</span>

                        {claim.source_ref !== '' && (
                            <cite className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.7rem] break-all text-foreground not-italic">
                                {claim.source_ref}
                            </cite>
                        )}
                    </footer>
                </blockquote>
            </div>
        </li>
    );
}

export function ClaimList({
    claims,
    className,
}: {
    claims: AnalysisClaim[];
    className?: string;
}) {
    if (claims.length === 0) {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                Nothing to check yet. Run an analysis and every claim on the CV
                that can be traced to real code will appear here, with the
                evidence next to it.
            </p>
        );
    }

    return (
        <div className={cn('space-y-4', className)}>
            <ul className="flex flex-wrap gap-2">
                {statusOrder.map((status) => {
                    const { label, icon: Icon, chip } = statusStyles[status];
                    const count = claims.filter(
                        (claim) => claim.status === status,
                    ).length;

                    return (
                        <li
                            key={status}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs',
                                chip,
                            )}
                        >
                            <Icon aria-hidden="true" className="size-3.5" />
                            <span className="font-semibold">{count}</span>
                            {label.toLowerCase()}
                        </li>
                    );
                })}
            </ul>

            <ul className="space-y-3">
                {claims.map((claim, index) => (
                    <ClaimRow key={`${claim.claim}-${index}`} claim={claim} />
                ))}
            </ul>
        </div>
    );
}
