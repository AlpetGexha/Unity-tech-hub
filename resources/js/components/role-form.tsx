import { Form } from '@inertiajs/react';
import JobController from '@/actions/App/Http/Controllers/JobController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export type RoleCandidate = {
    id: number;
    name: string;
    evaluation_status?: string | null;
};

export type Role = {
    id: number;
    title: string;
    requirements: string;
    priorities: string;
    created_at?: string | null;
    candidates?: RoleCandidate[];
};

type RoleFormProps = {
    job?: Role | null;
};

export function RoleForm({ job = null }: RoleFormProps) {
    const form = job
        ? JobController.update.form(job)
        : JobController.store.form();

    return (
        <Form
            {...form}
            key={job?.id ?? 'new'}
            disableWhileProcessing
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Role title</Label>

                        <Input
                            id="title"
                            name="title"
                            required
                            autoComplete="off"
                            defaultValue={job?.title ?? ''}
                            placeholder="Senior Backend Engineer"
                            aria-describedby="title-help"
                            aria-invalid={errors.title ? true : undefined}
                        />

                        <p
                            id="title-help"
                            className="text-sm text-muted-foreground"
                        >
                            The title candidates will see on the report.
                        </p>

                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="requirements">
                            What the role needs
                        </Label>

                        <Textarea
                            id="requirements"
                            name="requirements"
                            required
                            rows={8}
                            className="min-h-40"
                            defaultValue={job?.requirements ?? ''}
                            placeholder={
                                'Production PHP and Laravel, at least three years\nDesigning and versioning REST APIs\nAutomated tests they wrote themselves'
                            }
                            aria-describedby="requirements-help"
                            aria-invalid={
                                errors.requirements ? true : undefined
                            }
                        />

                        <p
                            id="requirements-help"
                            className="text-sm text-muted-foreground"
                        >
                            One requirement per line. These become the criteria
                            every candidate is scored against.
                        </p>

                        <InputError message={errors.requirements} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="priorities">What matters most</Label>

                        <Textarea
                            id="priorities"
                            name="priorities"
                            rows={5}
                            className="min-h-28"
                            defaultValue={job?.priorities ?? ''}
                            placeholder={
                                'Real production code counts for more than side projects.\nWe would rather see honest gaps than padded claims.'
                            }
                            aria-describedby="priorities-help"
                            aria-invalid={errors.priorities ? true : undefined}
                        />

                        <p
                            id="priorities-help"
                            className="text-sm text-muted-foreground"
                        >
                            Say which requirements carry the most weight, and
                            why. This shapes how the scores are balanced.
                        </p>

                        <InputError message={errors.priorities} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {job ? 'Update role' : 'Save role'}
                    </Button>
                </>
            )}
        </Form>
    );
}
