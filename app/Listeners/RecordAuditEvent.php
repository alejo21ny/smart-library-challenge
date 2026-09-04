<?php

namespace App\Listeners;

use App\Enums\AuditEventType;
use App\Events\BookBorrowed;
use App\Events\BookCreated;
use App\Events\BookDeleted;
use App\Events\BookReturned;
use App\Events\BookUpdated;
use App\Events\UserRoleChanged;
use App\Models\AuditEvent;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Events\Dispatcher;

/**
 * The single place every domain event that matters becomes an AuditEvent row.
 * Keeps "write an audit log entry" out of every Action/Controller — they just
 * dispatch a domain event, this subscriber does the recording.
 */
class RecordAuditEvent
{
    public function handleBookCreated(BookCreated $event): void
    {
        $this->record($event->actor, AuditEventType::BookCreated, Book::class, $event->book->id, [
            'title' => $event->book->title,
        ]);
    }

    public function handleBookUpdated(BookUpdated $event): void
    {
        $this->record($event->actor, AuditEventType::BookUpdated, Book::class, $event->book->id, [
            'changes' => $event->changes,
        ]);
    }

    public function handleBookDeleted(BookDeleted $event): void
    {
        $this->record($event->actor, AuditEventType::BookDeleted, Book::class, $event->bookId, [
            'title' => $event->title,
        ]);
    }

    public function handleBookBorrowed(BookBorrowed $event): void
    {
        // The book title and borrower name are captured here, at the moment
        // of the event, rather than resolved later via the subject_id/loan_id
        // relation — so the audit row stays an accurate historical record
        // even if the book is later renamed or the loan/book row is gone.
        $this->record($event->actor, AuditEventType::BookBorrowed, Loan::class, $event->loan->id, [
            'book_id' => $event->loan->book_id,
            'book_title' => $event->loan->book->title,
            'user_id' => $event->loan->user_id,
            'borrower_name' => $event->loan->user->name,
            'due_at' => $event->loan->due_at->toIso8601String(),
        ]);
    }

    public function handleBookReturned(BookReturned $event): void
    {
        $this->record($event->actor, AuditEventType::BookReturned, Loan::class, $event->loan->id, [
            'book_id' => $event->loan->book_id,
            'book_title' => $event->loan->book->title,
            'user_id' => $event->loan->user_id,
            'borrower_name' => $event->loan->user->name,
            'returned_at' => $event->loan->returned_at?->toIso8601String(),
        ]);
    }

    public function handleUserRoleChanged(UserRoleChanged $event): void
    {
        $this->record($event->actor, AuditEventType::UserRoleChanged, User::class, $event->user->id, [
            'old_role' => $event->oldRole->value,
            'new_role' => $event->newRole->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(?User $actor, AuditEventType $type, string $subjectType, int $subjectId, array $metadata): void
    {
        AuditEvent::query()->create([
            'user_id' => $actor?->id,
            'event_type' => $type->value,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            BookCreated::class => 'handleBookCreated',
            BookUpdated::class => 'handleBookUpdated',
            BookDeleted::class => 'handleBookDeleted',
            BookBorrowed::class => 'handleBookBorrowed',
            BookReturned::class => 'handleBookReturned',
            UserRoleChanged::class => 'handleUserRoleChanged',
        ];
    }
}
