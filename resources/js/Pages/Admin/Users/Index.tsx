import Pagination, { Paginated } from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    loans_count: number;
}

interface RoleOption {
    value: string;
    label: string;
}

export default function AdminUsersIndex({
    users,
    roles,
}: {
    users: Paginated<UserRow>;
    roles: RoleOption[];
}) {
    const currentUser = usePage().props.auth.user as unknown as { id: number };

    function changeRole(user: UserRow, role: string) {
        router.patch(
            route('admin.users.role', user.id),
            { role },
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">Users</h1>
            }
        >
            <Head title="Users" />

            <div className="overflow-x-auto rounded-lg border border-line dark:border-line-dark">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-line bg-paper-raised text-xs uppercase tracking-wide text-ink-faint dark:border-line-dark dark:bg-paper-dark-raised dark:text-ink-dark-faint">
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Loans</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr
                                key={user.id}
                                className="border-b border-line last:border-0 dark:border-line-dark"
                            >
                                <td className="px-4 py-3">{user.name}</td>
                                <td className="px-4 py-3 text-ink-muted dark:text-ink-dark-muted">
                                    {user.email}
                                </td>
                                <td className="px-4 py-3 text-ink-muted dark:text-ink-dark-muted">
                                    {user.loans_count}
                                </td>
                                <td className="px-4 py-3">
                                    <select
                                        value={user.role}
                                        onChange={(e) =>
                                            changeRole(user, e.target.value)
                                        }
                                        disabled={user.id === currentUser.id}
                                        aria-label={`Role for ${user.name}`}
                                        className="input py-1"
                                    >
                                        {roles.map((r) => (
                                            <option
                                                key={r.value}
                                                value={r.value}
                                            >
                                                {r.label}
                                            </option>
                                        ))}
                                    </select>
                                    {user.id === currentUser.id && (
                                        <span className="ml-2 text-xs text-ink-faint dark:text-ink-dark-faint">
                                            (you)
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination pagination={users} />
        </AuthenticatedLayout>
    );
}
