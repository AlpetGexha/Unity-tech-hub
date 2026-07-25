import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import InterviewController from '@/actions/App/Http/Controllers/InterviewController';
import type { Recommendation } from '@/types/analysis';

const recommendationLabels: Record<Recommendation, string> = {
    strong_yes: 'Strong yes',
    yes: 'Yes',
    maybe: 'Maybe',
    no: 'No',
};

/**
 * Weight-not-hue convention: recommendations aren't traffic-lighted, since a
 * "no" is a legitimate, considered outcome rather than a failure state.
 */
const recommendationStyles: Record<Recommendation, string> = {
    strong_yes: 'bg-primary text-primary-foreground',
    yes: 'bg-primary text-primary-foreground',
    maybe: 'bg-secondary text-secondary-foreground',
    no: 'bg-secondary text-secondary-foreground',
};

export type EvaluationOverviewCandidate = {
    id: number;
    name: string;
    job_title: string | null;
    fit_score: number | null;
    recommendation: Recommendation | null;
    analyzed_at: string | null;
    interview_status: InterviewStatus | null;
    can_start_ai_interview: boolean;
};

export type InterviewStatus =
    'waiting_for_interview' | 'approved' | 'rejected' | 'interviewed';

const interviewStatusOptions: Array<{
    value: InterviewStatus | '';
    label: string;
}> = [
    { value: '', label: 'No status' },
    { value: 'waiting_for_interview', label: 'Waiting for interview' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'interviewed', label: 'Interviewed' },
];

export function CandidatesTable({
    candidates,
}: {
    candidates: EvaluationOverviewCandidate[];
}) {
    const [updatingCandidateId, setUpdatingCandidateId] = useState<
        number | null
    >(null);

    const updateStatus = (
        candidateId: number,
        value: InterviewStatus | '',
    ): void => {
        setUpdatingCandidateId(candidateId);

        router.patch(
            CandidateController.updateStatus.url(candidateId),
            { interview_status: value === '' ? null : value },
            {
                preserveScroll: true,
                onFinish: () => setUpdatingCandidateId(null),
            },
        );
    };

    return (
        <div className="overflow-hidden rounded-xl border bg-card text-card-foreground">
            <table className="w-full text-left text-sm">
                <thead className="border-b bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3 font-medium">Candidate</th>
                        <th className="px-4 py-3 font-medium">Role</th>
                        <th className="px-4 py-3 font-medium">Fit score</th>
                        <th className="px-4 py-3 font-medium">
                            Recommendation
                        </th>
                        <th className="px-4 py-3 font-medium">Interview</th>
                        <th className="px-4 py-3 font-medium">
                            <span className="sr-only">Evaluation</span>
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
                                            candidate.recommendation
                                        ] ?? candidate.recommendation}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                        Not yet evaluated
                                    </span>
                                )}
                            </td>

                            <td className="space-y-2 px-4 py-3">
                                <label
                                    className="sr-only"
                                    htmlFor={`interview-status-${candidate.id}`}
                                >
                                    Interview status for {candidate.name}
                                </label>

                                <select
                                    id={`interview-status-${candidate.id}`}
                                    value={candidate.interview_status ?? ''}
                                    disabled={
                                        updatingCandidateId === candidate.id
                                    }
                                    onChange={(event) =>
                                        updateStatus(
                                            candidate.id,
                                            event.target.value as
                                                InterviewStatus | '',
                                        )
                                    }
                                    className="h-9 max-w-full rounded-md border border-input bg-background px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {interviewStatusOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>

                                {candidate.can_start_ai_interview && (
                                    <Link
                                        href={InterviewController.index(
                                            candidate.id,
                                        )}
                                        className="block font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        Start AI interview
                                    </Link>
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
    );
}
