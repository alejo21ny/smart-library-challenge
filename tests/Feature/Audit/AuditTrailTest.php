<?php

use App\Models\AuditEvent;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

test('creating a book records a BOOK_CREATED audit event', function () {
    $librarian = User::factory()->librarian()->create();

    $this->actingAs($librarian)->post(route('admin.books.store'), [
        'title' => 'Audited Book',
        'author' => 'Someone',
        'tags' => [],
    ]);

    $book = Book::query()->where('title', 'Audited Book')->firstOrFail();

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'BOOK_CREATED',
        'subject_type' => Book::class,
        'subject_id' => $book->id,
        'user_id' => $librarian->id,
    ]);
});

test('updating a book records a BOOK_UPDATED audit event with the changed fields', function () {
    $librarian = User::factory()->librarian()->create();
    $book = Book::factory()->create(['title' => 'Before']);

    $this->actingAs($librarian)->put(route('admin.books.update', $book), [
        'title' => 'After',
        'author' => $book->author,
        'tags' => [],
    ]);

    $event = AuditEvent::query()
        ->where('event_type', 'BOOK_UPDATED')
        ->where('subject_id', $book->id)
        ->firstOrFail();

    expect($event->metadata['changes']['title'])->toBe('After');
});

test('deleting a book records a BOOK_DELETED audit event', function () {
    $librarian = User::factory()->librarian()->create();
    $book = Book::factory()->create();
    $bookId = $book->id;

    $this->actingAs($librarian)->delete(route('admin.books.destroy', $book));

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'BOOK_DELETED',
        'subject_type' => Book::class,
        'subject_id' => $bookId,
    ]);
});

test('borrowing and returning a book records both audit events', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create(['title' => 'The Audited Loan']);

    $this->actingAs($member)->post(route('catalog.borrow', $book));
    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'BOOK_BORROWED',
        'subject_type' => Loan::class,
        'subject_id' => $loan->id,
        'user_id' => $member->id,
    ]);

    $borrowedEvent = AuditEvent::query()->where('event_type', 'BOOK_BORROWED')->where('subject_id', $loan->id)->firstOrFail();
    expect($borrowedEvent->metadata)->toMatchArray(['book_title' => 'The Audited Loan', 'borrower_name' => $member->name]);

    $this->actingAs($member)->post(route('loans.return', $loan));

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'BOOK_RETURNED',
        'subject_type' => Loan::class,
        'subject_id' => $loan->id,
        'user_id' => $member->id,
    ]);

    $returnedEvent = AuditEvent::query()->where('event_type', 'BOOK_RETURNED')->where('subject_id', $loan->id)->firstOrFail();
    expect($returnedEvent->metadata)->toMatchArray(['book_title' => 'The Audited Loan', 'borrower_name' => $member->name]);
});

test('changing a user\'s role records a USER_ROLE_CHANGED audit event', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();

    $this->actingAs($admin)->patch(route('admin.users.role', $member), ['role' => 'librarian']);

    $event = AuditEvent::query()
        ->where('event_type', 'USER_ROLE_CHANGED')
        ->where('subject_id', $member->id)
        ->firstOrFail();

    expect($event->metadata)->toMatchArray(['old_role' => 'member', 'new_role' => 'librarian']);
    expect($event->user_id)->toBe($admin->id);
});
