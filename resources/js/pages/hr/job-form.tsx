import { Head } from '@inertiajs/react';
import JobController from '@/actions/App/Http/Controllers/JobController';
import { RoleForm } from '@/components/role-form';
import type { Role } from '@/components/role-form';

export default function JobForm({ job = null }: { job?: Role | null }) {
    const isEditing = job !== null;

    return (
        <>
            <Head title={isEditing ? 'Edit role' : 'Create role'} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {isEditing ? 'Edit role' : 'Create role'}
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Describe the role once. Every candidate you add is
                        checked against what you write here.
                    </p>
                </header>

                <RoleForm job={job} />
            </div>
        </>
    );
}

JobForm.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: JobController.index(),
        },
        {
            title: 'Role form',
            href: JobController.create(),
        },
    ],
};
