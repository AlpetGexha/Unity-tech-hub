import { Form, Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type SavedCandidate = {
    id: number | string;
    name: string;
};

export default function CandidateForm({
    candidate,
}: {
    candidate?: SavedCandidate;
}) {
    const cvField = useRef<HTMLTextAreaElement>(null);
    const [fileError, setFileError] = useState<string>('');

    /**
     * Pasting is the primary path. Reading a plain-text file only fills the
     * same textarea, so nothing about the upload has to reach the server.
     */
    const fillFromFile = async (
        event: ChangeEvent<HTMLInputElement>,
    ): Promise<void> => {
        const file = event.target.files?.[0];

        event.target.value = '';

        if (!file) {
            return;
        }

        try {
            const text = await file.text();

            if (cvField.current) {
                cvField.current.value = text;
                cvField.current.focus();
            }

            setFileError('');
        } catch {
            setFileError(
                'We could not read that file. Open it, copy the text, and paste it into the box above.',
            );
        }
    };

    return (
        <>
            <Head title="Add a candidate" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Add a candidate
                    </h1>

                    <p className="text-sm text-muted-foreground">
                        Paste the CV and, if they have one, their GitHub
                        username. We check what the CV claims against the code
                        they have actually published.
                    </p>
                </header>

                {candidate && (
                    <Form
                        action={`/hr/candidates/${candidate.id}/analyze`}
                        method="post"
                        disableWhileProcessing
                        className="space-y-3 rounded-xl border bg-card p-5 text-card-foreground"
                    >
                        {({ processing }) => (
                            <>
                                <h2 className="font-medium">
                                    {candidate.name} is saved
                                </h2>

                                <p className="text-sm text-muted-foreground">
                                    Analysing reads their public GitHub and
                                    checks every claim on the CV against real
                                    code. It takes about twenty seconds.
                                </p>

                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {processing
                                        ? 'Checking the code'
                                        : 'Analyse this CV'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <Form
                    action="/hr/candidates"
                    method="post"
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="off"
                                    placeholder="Morgan Kelsey"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cv_text">CV text</Label>

                                <Textarea
                                    id="cv_text"
                                    name="cv_text"
                                    ref={cvField}
                                    required
                                    rows={14}
                                    className="min-h-64 font-mono text-sm"
                                    placeholder="Paste the whole CV here."
                                    aria-describedby="cv_text-help"
                                    aria-invalid={
                                        errors.cv_text ? true : undefined
                                    }
                                />

                                <p
                                    id="cv_text-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Paste the whole CV. Layout and formatting do
                                    not matter, only the words.
                                </p>

                                <InputError message={errors.cv_text} />

                                <div className="mt-1 flex flex-wrap items-center gap-3">
                                    <Label
                                        htmlFor="cv_file"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Or load a .txt or .md file
                                    </Label>

                                    <Input
                                        id="cv_file"
                                        type="file"
                                        accept=".txt,.md,text/plain,text/markdown"
                                        onChange={fillFromFile}
                                        className="h-auto max-w-xs py-1.5 text-xs"
                                    />
                                </div>

                                <InputError message={fileError} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="github_username">
                                    GitHub username
                                </Label>

                                <Input
                                    id="github_username"
                                    name="github_username"
                                    autoComplete="off"
                                    spellCheck={false}
                                    placeholder="mkelsey"
                                    aria-describedby="github_username-help"
                                    aria-invalid={
                                        errors.github_username
                                            ? true
                                            : undefined
                                    }
                                />

                                <p
                                    id="github_username-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Public repositories only. Without this we
                                    can still read the CV, but we cannot check
                                    it against any code.
                                </p>

                                <InputError message={errors.github_username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="linkedin_text">
                                    LinkedIn profile text
                                </Label>

                                <Textarea
                                    id="linkedin_text"
                                    name="linkedin_text"
                                    rows={6}
                                    className="min-h-32"
                                    placeholder="Paste the profile text, if you have it."
                                    aria-describedby="linkedin_text-help"
                                    aria-invalid={
                                        errors.linkedin_text ? true : undefined
                                    }
                                />

                                <p
                                    id="linkedin_text-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Paste the text rather than a link. Leave it
                                    blank if you do not have it.
                                </p>

                                <InputError message={errors.linkedin_text} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save candidate
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CandidateForm.layout = {
    breadcrumbs: [
        {
            title: 'Add a candidate',
            href: '/hr/candidates/create',
        },
    ],
};
