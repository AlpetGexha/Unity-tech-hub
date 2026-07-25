import { Form, Head } from '@inertiajs/react';
import JobController from '@/actions/App/Http/Controllers/JobController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export default function Jobs() {
    return (
        <>
            <Head title="Add a role" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add a role
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Describe the role once. Every candidate you add is
                        checked against what you write here.
                    </p>
                </header>

                <Form
                    {...JobController.store.form()}
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
                                    placeholder="Senior Backend Engineer"
                                    aria-describedby="title-help"
                                    aria-invalid={
                                        errors.title ? true : undefined
                                    }
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
                                    One requirement per line. These become the
                                    criteria every candidate is scored against.
                                </p>

                                <InputError message={errors.requirements} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="priorities">
                                    What matters most
                                </Label>

                                <Textarea
                                    id="priorities"
                                    name="priorities"
                                    rows={5}
                                    className="min-h-28"
                                    placeholder={
                                        'Real production code counts for more than side projects.\nWe would rather see honest gaps than padded claims.'
                                    }
                                    aria-describedby="priorities-help"
                                    aria-invalid={
                                        errors.priorities ? true : undefined
                                    }
                                />

                                <p
                                    id="priorities-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Say which requirements carry the most
                                    weight, and why. This shapes how the scores
                                    are balanced.
                                </p>

                                <InputError message={errors.priorities} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save role
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Jobs.layout = {
    breadcrumbs: [
        {
            title: 'Add a role',
            href: JobController.index(),
        },
    ],
};
