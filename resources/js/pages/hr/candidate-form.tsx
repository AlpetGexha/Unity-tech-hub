import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import CandidateController from '@/actions/App/Http/Controllers/CandidateController';
import JobController from '@/actions/App/Http/Controllers/JobController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import candidateImports from '@/routes/hr/candidate-imports';

type ExtractedProfile = {
    name: string;
    github_username: string;
    linkedin_text: string;
};

type PreviewResponse = {
    preview_token: string;
    profile: ExtractedProfile;
};

type SelectableJob = {
    id: number;
    title: string;
};

/**
 * A native select rather than the Radix one: it submits its value inside an
 * uncontrolled Inertia `<Form>` without extra wiring, and `required` gets
 * browser validation for free, exactly like the other fields here.
 */
const selectClasses =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base text-foreground shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40';

const pageHeader = (
    <header className="space-y-1">
        <h1 className="text-xl font-semibold tracking-tight">
            Add a candidate
        </h1>

        <p className="text-sm text-muted-foreground">
            Upload the CV as a PDF and, if they have one, their GitHub username.
            We check what the CV claims against the code they have actually
            published.
        </p>
    </header>
);

export default function CandidateForm({
    jobs = [],
}: {
    jobs?: SelectableJob[];
}) {
    const [fileError, setFileError] = useState<string>('');
    const [profile, setProfile] = useState<ExtractedProfile>({
        name: '',
        github_username: '',
        linkedin_text: '',
    });
    const [previewToken, setPreviewToken] = useState<string>('');
    const [profileStatus, setProfileStatus] = useState<
        'idle' | 'loading' | 'ready' | 'error'
    >('idle');
    const [profileError, setProfileError] = useState<string>('');

    /**
     * Preview the PDF as soon as it is selected so OpenAI can fill the editable
     * candidate fields before the HR user saves the record.
     */
    const validateFile = async (
        event: ChangeEvent<HTMLInputElement>,
    ): Promise<void> => {
        const file = event.target.files?.[0];

        if (!file) {
            setFileError('');
            setProfile({ name: '', github_username: '', linkedin_text: '' });
            setPreviewToken('');
            setProfileStatus('idle');
            setProfileError('');

            return;
        }

        if (file.type !== 'application/pdf') {
            event.target.value = '';
            setFileError('Please choose a PDF file.');
            setProfile({ name: '', github_username: '', linkedin_text: '' });
            setPreviewToken('');
            setProfileStatus('idle');
            setProfileError('');

            return;
        }

        setFileError('');
        setProfile({ name: '', github_username: '', linkedin_text: '' });
        setPreviewToken('');
        setProfileStatus('loading');
        setProfileError('');

        const formData = new FormData();
        formData.append('cv_file', file);

        try {
            const csrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );
            const response = await fetch(CandidateController.preview.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
                },
                body: formData,
            });
            const payload = (await response.json()) as Partial<
                PreviewResponse & {
                    message: string;
                    errors: Record<string, string[]>;
                }
            >;

            if (!response.ok || !payload.preview_token || !payload.profile) {
                throw new Error(
                    payload.errors?.cv_file?.[0] ??
                        payload.message ??
                        'The PDF could not be read. Please try again.',
                );
            }

            setProfile(payload.profile);
            setPreviewToken(payload.preview_token);
            setProfileStatus('ready');
        } catch (error) {
            setProfileStatus('error');
            setProfileError(
                error instanceof Error
                    ? error.message
                    : 'The PDF could not be read. Please try again.',
            );
        }
    };

    if (jobs.length === 0) {
        return (
            <>
                <Head title="Add a candidate" />

                <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                    {pageHeader}

                    <div className="space-y-3 rounded-xl border bg-card p-6 text-card-foreground">
                        <h2 className="font-medium">Add a role first</h2>

                        <p className="max-w-prose text-sm text-muted-foreground">
                            Candidates are scored against a role&rsquo;s
                            requirements, so there has to be a role to score
                            them against. It takes a minute.
                        </p>

                        <Button asChild>
                            <Link href={JobController.index()}>Add a role</Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Add a candidate" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 md:p-6">
                {pageHeader}

                <Form
                    {...candidateImports.store.form()}
                    encType="multipart/form-data"
                    disableWhileProcessing
                    className="space-y-6 rounded-xl border bg-card p-5 text-card-foreground"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-1">
                                <h2 className="font-medium">
                                    Evaluate candidates from CSV
                                </h2>

                                <p className="text-sm text-muted-foreground">
                                    Choose one role, upload a CSV, and every
                                    valid row will be evaluated in the queue at
                                    the same time.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="batch_job_id">Role</Label>

                                <select
                                    id="batch_job_id"
                                    name="job_id"
                                    required
                                    defaultValue=""
                                    className={selectClasses}
                                >
                                    <option value="" disabled>
                                        Choose a role
                                    </option>

                                    {jobs.map((job) => (
                                        <option key={job.id} value={job.id}>
                                            {job.title}
                                        </option>
                                    ))}
                                </select>

                                <InputError message={errors.job_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="csv_file">Candidate CSV</Label>

                                <Input
                                    id="csv_file"
                                    name="csv_file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    className="h-auto py-1.5"
                                    aria-describedby="csv_file-help"
                                    aria-invalid={
                                        errors.csv_file ? true : undefined
                                    }
                                />

                                <p
                                    id="csv_file-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Required headers:{' '}
                                    <code className="text-xs">
                                        name, cv_markdown
                                    </code>
                                    . Optional:{' '}
                                    <code className="text-xs">
                                        github_username, linkedin_text
                                    </code>
                                    .
                                </p>

                                <InputError message={errors.csv_file} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {processing
                                    ? 'Starting evaluation'
                                    : 'Evaluate CSV candidates'}
                            </Button>
                        </>
                    )}
                </Form>

                <Form
                    {...CandidateController.store.form()}
                    encType="multipart/form-data"
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="job_id">Role</Label>

                                <select
                                    id="job_id"
                                    name="job_id"
                                    required
                                    defaultValue=""
                                    className={selectClasses}
                                    aria-describedby="job_id-help"
                                    aria-invalid={
                                        errors.job_id ? true : undefined
                                    }
                                >
                                    <option value="" disabled>
                                        Choose a role
                                    </option>

                                    {jobs.map((job) => (
                                        <option key={job.id} value={job.id}>
                                            {job.title}
                                        </option>
                                    ))}
                                </select>

                                <p
                                    id="job_id-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    This candidate is scored against the
                                    requirements you saved for this role.
                                </p>

                                <InputError message={errors.job_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="off"
                                    placeholder="Morgan Kelsey"
                                    value={profile.name}
                                    onChange={(event) =>
                                        setProfile((current) => ({
                                            ...current,
                                            name: event.target.value,
                                        }))
                                    }
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cv_file">CV (PDF)</Label>

                                <Input
                                    id="cv_file"
                                    name="cv_file"
                                    type="file"
                                    required
                                    accept="application/pdf"
                                    onChange={validateFile}
                                    aria-describedby="cv_file-help"
                                    aria-invalid={
                                        errors.cv_file || fileError
                                            ? true
                                            : undefined
                                    }
                                />

                                <p
                                    id="cv_file-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Upload the CV as a PDF. We convert it to
                                    text automatically before scoring it.
                                </p>

                                <InputError message={errors.cv_file} />
                                <InputError message={fileError} />
                                <InputError message={profileError} />
                                <input
                                    type="hidden"
                                    name="cv_preview_token"
                                    value={previewToken}
                                />

                                {profileStatus === 'loading' && (
                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Spinner /> Reading the PDF and finding
                                        profile details&hellip;
                                    </p>
                                )}

                                {profileStatus === 'ready' && (
                                    <p className="text-sm text-muted-foreground">
                                        AI found these details. Review them and
                                        edit anything before saving.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="github_username">
                                    GitHub username (optional)
                                </Label>

                                <Input
                                    id="github_username"
                                    name="github_username"
                                    autoComplete="off"
                                    spellCheck={false}
                                    placeholder="mkelsey"
                                    value={profile.github_username}
                                    onChange={(event) =>
                                        setProfile((current) => ({
                                            ...current,
                                            github_username: event.target.value,
                                        }))
                                    }
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
                                    Public repositories only. This is what the
                                    CV gets checked against when it is
                                    available.
                                </p>

                                <InputError message={errors.github_username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="linkedin_text">
                                    LinkedIn profile text (optional)
                                </Label>

                                <Textarea
                                    id="linkedin_text"
                                    name="linkedin_text"
                                    rows={6}
                                    className="min-h-32"
                                    placeholder="Paste the profile text, if you have it."
                                    value={profile.linkedin_text}
                                    onChange={(event) =>
                                        setProfile((current) => ({
                                            ...current,
                                            linkedin_text: event.target.value,
                                        }))
                                    }
                                    aria-describedby="linkedin_text-help"
                                    aria-invalid={
                                        errors.linkedin_text ? true : undefined
                                    }
                                />

                                <p
                                    id="linkedin_text-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    AI may find profile text or a link. Review
                                    it and leave it blank if unavailable.
                                </p>

                                <InputError message={errors.linkedin_text} />
                            </div>

                            <Button
                                type="submit"
                                disabled={
                                    processing || profileStatus === 'loading'
                                }
                            >
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
            href: CandidateController.create(),
        },
    ],
};
