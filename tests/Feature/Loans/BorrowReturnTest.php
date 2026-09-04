<?php

use App\Actions\Loan\BorrowBookAction;
use App\Exceptions\BookUnavailableException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a member can borrow an available book', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $response = $this->actingAs($member)->post(route('catalog.borrow', $book));

    $response->assertRedirect();
    $this->assertDatabaseHas('loans', [
        'book_id' => $book->id,
        'user_id' => $member->id,
        'returned_at' => null,
    ]);
});

test('the due date is set from the configured loan period', function () {
    config(['library.loan_period_days' => 14]);
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->post(route('catalog.borrow', $book));

    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();
    $days = $loan->borrowed_at->diffInDays($loan->due_at);
    expect($days)->toBeGreaterThanOrEqual(13)->toBeLessThanOrEqual(14);
});

test('a borrowed book becomes unavailable in the catalog', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->post(route('catalog.borrow', $book));

    expect($book->fresh()->availability)->toBe('borrowed');
});

test('borrowing an already-borrowed book is rejected with a clean error', function () {
    $book = Book::factory()->create();
    $firstBorrower = User::factory()->member()->create();
    $secondBorrower = User::factory()->member()->create();

    $this->actingAs($firstBorrower)->post(route('catalog.borrow', $book));
    $response = $this->actingAs($secondBorrower)->post(route('catalog.borrow', $book));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Loan::query()->where('book_id', $book->id)->whereNull('returned_at')->count())->toBe(1);
});

test('the database itself rejects a second active loan for the same book', function () {
    // Bypasses the application-level lock entirely to prove the PostgreSQL
    // partial unique index (loans_book_id_active_unique) is the real guarantee,
    // not just the lockForUpdate() check in BorrowBookAction.
    $book = Book::factory()->create();
    $userA = User::factory()->member()->create();
    $userB = User::factory()->member()->create();

    Loan::query()->create([
        'book_id' => $book->id,
        'user_id' => $userA->id,
        'borrowed_at' => now(),
        'due_at' => now()->addDays(14),
    ]);

    expect(fn () => DB::table('loans')->insert([
        'book_id' => $book->id,
        'user_id' => $userB->id,
        'borrowed_at' => now(),
        'due_at' => now()->addDays(14),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('BorrowBookAction throws a domain exception, not a raw database error, on conflict', function () {
    $book = Book::factory()->create();
    $userA = User::factory()->member()->create();
    $userB = User::factory()->member()->create();

    (new BorrowBookAction)->execute($book, $userA, $userA);

    expect(fn () => (new BorrowBookAction)->execute($book->fresh(), $userB, $userB))
        ->toThrow(BookUnavailableException::class);
});

test('a member can return their own borrowed book', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();
    $this->actingAs($member)->post(route('catalog.borrow', $book));
    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();

    $response = $this->actingAs($member)->post(route('loans.return', $loan));

    $response->assertRedirect();
    expect($loan->fresh()->returned_at)->not->toBeNull();
    expect($book->fresh()->availability)->toBe('available');
});

test('after return, the book can be borrowed again', function () {
    $member = User::factory()->member()->create();
    $another = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->post(route('catalog.borrow', $book));
    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();
    $this->actingAs($member)->post(route('loans.return', $loan));

    $response = $this->actingAs($another)->post(route('catalog.borrow', $book));

    $response->assertSessionMissing('error');
    $this->assertDatabaseHas('loans', ['book_id' => $book->id, 'user_id' => $another->id, 'returned_at' => null]);
});

test('a member cannot return someone else\'s loan', function () {
    $owner = User::factory()->member()->create();
    $intruder = User::factory()->member()->create();
    $book = Book::factory()->create();
    $this->actingAs($owner)->post(route('catalog.borrow', $book));
    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();

    $response = $this->actingAs($intruder)->post(route('loans.return', $loan));

    $response->assertForbidden();
    expect($loan->fresh()->returned_at)->toBeNull();
});

test('a loan past its due date is reported as overdue', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();
    $loan = Loan::factory()->overdue()->create(['book_id' => $book->id, 'user_id' => $member->id]);

    expect($loan->isOverdue())->toBeTrue();
    expect(Loan::query()->overdue()->pluck('id'))->toContain($loan->id);
});

test('a returned loan is never reported as overdue even if it was late', function () {
    $loan = Loan::factory()->overdue()->returned()->create();

    expect($loan->isOverdue())->toBeFalse();
    expect(Loan::query()->overdue()->pluck('id'))->not->toContain($loan->id);
});

test('librarian can check out a book on behalf of a member', function () {
    $librarian = User::factory()->librarian()->create();
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $response = $this->actingAs($librarian)->post(route('admin.loans.store'), [
        'book_id' => $book->id,
        'user_id' => $member->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('loans', ['book_id' => $book->id, 'user_id' => $member->id]);
});

test('member cannot access the circulation (check out on behalf of) page', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)->get(route('admin.loans.index'))->assertForbidden();
});
