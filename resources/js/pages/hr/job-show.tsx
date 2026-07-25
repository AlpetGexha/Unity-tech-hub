import { Head, Link } from '@inertiajs/react';
import JobController from '@/actions/App/Http/Controllers/JobController';
import type { Role, RoleCandidate } from '@/components/role-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as candidateShow } from '@/routes/hr/candidates';

function RoleText({ children }: { children: string }) {
    return (
        <p className="text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
            {children}
        </p>
    );
}

function CandidateRow({ candidate }: { candidate: RoleCandidate }) {
    return (
        <li className="flex items-center justify-between gap-4 border-b py-3 last:border-b-0">
            <span className="font-medium">{candidate.name}</span>

            <Button asChild size="sm" variant="outline">
                <Link href={candidateShow(candidate.id)}>View candidate</Link>
            </Button>
        </li>
    );
}

export default function JobShow({ job }: { job: Role }) {
    const candidates = job.candidates ?? [];

    return (
        <>
            <Head title={job.title} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">Role</p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {job.title}
                        </h1>
                    </div>

                    <Button asChild>
                        <Link href={JobController.edit(job.id)}>Edit role</Link>
                    </Button>
                </header>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Requirements</CardTitle>
                            <CardDescription>
                                What candidates need to bring to the role.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RoleText>{job.requirements}</RoleText>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Priorities</CardTitle>
                            <CardDescription>
                                What should carry the most weight in evaluation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RoleText>{job.priorities}</RoleText>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Candidates</CardTitle>
                        <CardDescription>
                            Candidates associated with this role.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {candidates.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No candidates have been added to this role yet.
                            </p>
                        ) : (
                            <ul>
                                {candidates.map((candidate) => (
                                    <CandidateRow
                                        key={candidate.id}
                                        candidate={candidate}
                                    />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

JobShow.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: JobController.index(),
        },
        {
            title: 'View role',
            href: JobController.index(),
        },
    ],
};
