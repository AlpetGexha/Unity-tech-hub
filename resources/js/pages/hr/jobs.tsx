import { Head, Link } from '@inertiajs/react';
import JobController from '@/actions/App/Http/Controllers/JobController';
import type { Role } from '@/components/role-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(date));
}

function RoleSummary({ text }: { text: string }) {
    return (
        <p className="line-clamp-2 text-sm whitespace-pre-line text-muted-foreground">
            {text}
        </p>
    );
}

export default function Jobs({ jobs = [] }: { jobs?: Role[] }) {
    return (
        <>
            <Head title="Roles" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Roles
                        </h1>

                        <p className="text-sm text-muted-foreground">
                            Manage the roles candidates are evaluated against.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={JobController.create()}>Create role</Link>
                    </Button>
                </header>

                {jobs.length === 0 ? (
                    <Card>
                        <CardContent className="space-y-3 pt-6">
                            <h2 className="font-medium">No roles yet</h2>

                            <p className="max-w-prose text-sm text-muted-foreground">
                                Create a role to start evaluating candidates
                                against clear requirements and priorities.
                            </p>

                            <Button asChild>
                                <Link href={JobController.create()}>
                                    Create your first role
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-xl border bg-card text-card-foreground">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Requirements
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Priorities
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Created
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {jobs.map((job) => (
                                    <tr key={job.id} className="align-top">
                                        <td className="px-4 py-4 font-medium">
                                            {job.title}
                                        </td>
                                        <td className="max-w-xs px-4 py-4">
                                            <RoleSummary
                                                text={job.requirements}
                                            />
                                        </td>
                                        <td className="max-w-xs px-4 py-4">
                                            <RoleSummary
                                                text={job.priorities}
                                            />
                                        </td>
                                        <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                            {formatDate(job.created_at)}
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={JobController.show(
                                                            job.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link
                                                        href={JobController.edit(
                                                            job.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            </div>
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

Jobs.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: JobController.index(),
        },
    ],
};
