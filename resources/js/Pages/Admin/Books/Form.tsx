import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Book } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useId } from 'react';

interface Props {
    book: Book | null;
    allTags: string[];
}

export default function BookForm({ book, allTags }: Props) {
    const isEditing = book !== null;
    const formId = useId();

    const { data, setData, post, put, transform, processing, errors } = useForm(
        {
            title: book?.title ?? '',
            author: book?.author ?? '',
            isbn: book?.isbn ?? '',
            description: book?.description ?? '',
            category: book?.category ?? '',
            publication_year: book?.publication_year
                ? String(book.publication_year)
                : '',
            cover_url: book?.cover_url ?? '',
            tags: book?.tags.map((t) => t.name).join(', ') ?? '',
        },
    );

    function submit(e: FormEvent) {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            tags: formData.tags
                .split(',')
                .map((t: string) => t.trim())
                .filter(Boolean),
        }));

        if (isEditing) {
            put(route('admin.books.update', book!.id));
        } else {
            post(route('admin.books.store'));
        }
    }

    const ids = {
        title: `${formId}-title`,
        author: `${formId}-author`,
        isbn: `${formId}-isbn`,
        category: `${formId}-category`,
        year: `${formId}-year`,
        cover: `${formId}-cover`,
        description: `${formId}-description`,
        tags: `${formId}-tags`,
    };

    return (
        <AuthenticatedLayout
            header={
                <h1 className="font-serif text-2xl font-semibold">
                    {isEditing ? 'Edit Book' : 'Add Book'}
                </h1>
            }
        >
            <Head title={isEditing ? 'Edit Book' : 'Add Book'} />

            <form
                onSubmit={submit}
                className="max-w-2xl rounded-lg border border-line bg-paper-raised p-6 dark:border-line-dark dark:bg-paper-dark-raised"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id={ids.title}
                        label="Title"
                        error={errors.title}
                        required
                    >
                        <input
                            id={ids.title}
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="input"
                            required
                        />
                    </Field>
                    <Field
                        id={ids.author}
                        label="Author"
                        error={errors.author}
                        required
                    >
                        <input
                            id={ids.author}
                            value={data.author}
                            onChange={(e) => setData('author', e.target.value)}
                            className="input"
                            required
                        />
                    </Field>
                    <Field id={ids.isbn} label="ISBN" error={errors.isbn}>
                        <input
                            id={ids.isbn}
                            value={data.isbn}
                            onChange={(e) => setData('isbn', e.target.value)}
                            className="input"
                        />
                    </Field>
                    <Field
                        id={ids.category}
                        label="Category"
                        error={errors.category}
                    >
                        <input
                            id={ids.category}
                            value={data.category}
                            onChange={(e) =>
                                setData('category', e.target.value)
                            }
                            className="input"
                        />
                    </Field>
                    <Field
                        id={ids.year}
                        label="Publication year"
                        error={errors.publication_year}
                    >
                        <input
                            id={ids.year}
                            type="number"
                            value={data.publication_year}
                            onChange={(e) =>
                                setData('publication_year', e.target.value)
                            }
                            className="input"
                        />
                    </Field>
                    <Field
                        id={ids.cover}
                        label="Cover URL"
                        error={errors.cover_url}
                    >
                        <input
                            id={ids.cover}
                            value={data.cover_url}
                            onChange={(e) =>
                                setData('cover_url', e.target.value)
                            }
                            className="input"
                            placeholder="https://…"
                        />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field
                            id={ids.description}
                            label="Description"
                            error={errors.description}
                        >
                            <textarea
                                id={ids.description}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                rows={4}
                                className="input"
                            />
                        </Field>
                    </div>
                    <div className="sm:col-span-2">
                        <Field
                            id={ids.tags}
                            label="Tags"
                            error={errors.tags}
                            hint={`Comma-separated. Existing: ${allTags.slice(0, 8).join(', ')}${allTags.length > 8 ? '…' : ''}`}
                        >
                            <input
                                id={ids.tags}
                                value={data.tags}
                                onChange={(e) =>
                                    setData('tags', e.target.value)
                                }
                                className="input"
                                placeholder="php, laravel, beginner-friendly"
                            />
                        </Field>
                    </div>
                </div>

                <div className="mt-6 flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-accent px-5 py-2.5 text-sm font-medium text-white hover:bg-accent-hover disabled:opacity-50 dark:bg-accent-dark dark:hover:bg-accent-dark-hover"
                    >
                        {isEditing ? 'Save changes' : 'Add book'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    required,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className="block text-sm font-medium text-ink-muted dark:text-ink-dark-muted"
            >
                {label}
                {required && (
                    <span
                        aria-hidden="true"
                        className="text-danger dark:text-danger-dark"
                    >
                        {' '}
                        *
                    </span>
                )}
            </label>
            {hint && (
                <p className="mt-0.5 text-xs text-ink-faint dark:text-ink-dark-faint">
                    {hint}
                </p>
            )}
            <div className="mt-1">{children}</div>
            {error && (
                <p
                    id={`${id}-error`}
                    className="mt-1 text-xs text-danger dark:text-danger-dark"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
