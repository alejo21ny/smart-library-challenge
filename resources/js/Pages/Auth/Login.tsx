import Checkbox from '@/Components/Checkbox';
import GoogleSignInButton from '@/Components/GoogleSignInButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const DEMO_ACCOUNTS = [
    { role: 'Admin', email: 'admin@example.test' },
    { role: 'Librarian', email: 'librarian@example.test' },
    { role: 'Member', email: 'member@example.test' },
] as const;

// Matches the documented demo password in README.md — never a real secret.
const DEMO_PASSWORD = 'password';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { googleOAuthEnabled } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    function fillDemoAccount(email: string) {
        setData({ email, password: DEMO_PASSWORD, remember: false });
    }

    return (
        <GuestLayout>
            <Head title="Log in" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {googleOAuthEnabled && <GoogleSignInButton />}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-ink-muted dark:text-ink-dark-muted">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-ink-muted underline hover:text-ink focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 dark:text-ink-dark-muted dark:hover:text-ink-dark dark:focus:ring-offset-paper-dark"
                        >
                            Forgot your password?
                        </Link>
                    )}

                    <PrimaryButton className="ms-4" disabled={processing}>
                        Log in
                    </PrimaryButton>
                </div>
            </form>

            <div className="mt-6 border-t border-line pt-4 dark:border-line-dark">
                <p className="text-xs font-medium uppercase tracking-wide text-ink-faint dark:text-ink-dark-faint">
                    Demo access
                </p>
                <p className="mt-1 text-xs text-ink-muted dark:text-ink-dark-muted">
                    Reviewing this project? Pick a role to fill the form above —
                    password is the same for all three demo accounts.
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                    {DEMO_ACCOUNTS.map((account) => (
                        <SecondaryButton
                            key={account.email}
                            type="button"
                            className="text-xs"
                            onClick={() => fillDemoAccount(account.email)}
                        >
                            {account.role}
                        </SecondaryButton>
                    ))}
                </div>
            </div>
        </GuestLayout>
    );
}
