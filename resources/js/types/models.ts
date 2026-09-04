export interface Tag {
    id: number;
    name: string;
}

export interface Book {
    id: number;
    title: string;
    author: string;
    isbn: string | null;
    description: string | null;
    category: string | null;
    publication_year: number | null;
    cover_url: string | null;
    availability: 'available' | 'borrowed';
    tags: Tag[];
    active_loan?: Loan | null;
    created_at: string;
    updated_at: string;
}

export interface LoanUser {
    id: number;
    name: string;
    email: string;
}

export interface Loan {
    id: number;
    book_id: number;
    user_id: number;
    borrowed_at: string;
    due_at: string;
    returned_at: string | null;
    book?: Book;
    user?: LoanUser;
}

export type UserRole = 'admin' | 'librarian' | 'member';

/** The dashboard's "Recent activity" feed — a human-readable summary already built server-side from the underlying audit_events row. See AuditEvent::describe(). */
export interface RecentActivityItem {
    id: number;
    description: string;
    created_at: string;
}
