<?php

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

test('an exact query finds the real book', function () {
    Book::factory()->create(['title' => 'Clean Architecture', 'author' => 'Robert C. Martin']);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'clean architecture']);

    $response->assertOk();
    expect(collect($response->json('books'))->pluck('title'))->toContain('Clean Architecture');
    expect($response->json('usedFuzzy'))->toBeFalse();
});

test('word order does not matter for a catalog match', function () {
    Book::factory()->create(['title' => 'Clean Architecture', 'author' => 'Robert C. Martin']);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'architecture clean']);

    $response->assertOk();
    expect(collect($response->json('books'))->pluck('title'))->toContain('Clean Architecture');
});

test('a misspelled, truncated query still finds the real book via fuzzy fallback', function () {
    Book::factory()->create(['title' => 'Clean Architecture', 'author' => 'Robert C. Martin']);
    Book::factory()->create(['title' => 'Completely Unrelated Novel', 'author' => 'Someone Else']);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'arquitecture clea']);

    $response->assertOk();
    $titles = collect($response->json('books'))->pluck('title');
    expect($titles)->toContain('Clean Architecture');
    expect($titles)->not->toContain('Completely Unrelated Novel');
    expect($response->json('usedFuzzy'))->toBeTrue();
});

test('a query with no credible match returns no results and no fabricated suggestion', function () {
    Book::factory()->create(['title' => 'Clean Architecture']);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'xyzzy quantum toaster nonsense']);

    $response->assertOk();
    expect($response->json('books'))->toBeEmpty();
    expect($response->json('suggestion'))->toBeNull();
});

test('a member asking about their own loans gets a real list of their loans, not a catalog search', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create(['title' => 'Designing Data-Intensive Applications']);
    Loan::factory()->create([
        'user_id' => $member->id,
        'book_id' => $book->id,
        'borrowed_at' => now()->subDays(2),
        'due_at' => now()->addDays(12),
    ]);

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'what books do I currently have borrowed?']);

    $response->assertOk();
    $response->assertJsonPath('action', 'get_my_loans');
    $loanBookTitles = collect($response->json('loans'))->pluck('book.title');
    expect($loanBookTitles)->toContain('Designing Data-Intensive Applications');
});

test('a member never sees another members loans through the assistant', function () {
    $member = User::factory()->member()->create();
    $otherMember = User::factory()->member()->create();
    $book = Book::factory()->create();
    Loan::factory()->create(['user_id' => $otherMember->id, 'book_id' => $book->id]);

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'what do I currently have borrowed']);

    $response->assertOk();
    expect($response->json('loans'))->toBeEmpty();
});

test('staff can ask for a circulation summary and get real counts', function () {
    Book::factory()->count(3)->create();
    $available = Book::factory()->create();
    $borrowed = Book::factory()->create();
    Loan::factory()->create(['book_id' => $borrowed->id, 'due_at' => now()->addDays(5)]);

    $librarian = User::factory()->librarian()->create();

    $response = $this->actingAs($librarian)->postJson(route('assistant.query'), ['query' => 'give me a quick circulation summary']);

    $response->assertOk();
    $response->assertJsonPath('action', 'get_library_summary');
    expect($response->json('summary.total'))->toBe(5);
    expect($response->json('summary.borrowed'))->toBe(1);
});

test('a member cannot reach the staff-only library summary through the assistant', function () {
    Book::factory()->count(3)->create();
    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'give me a quick circulation summary']);

    $response->assertOk();
    // Never a summary payload for a non-staff user, regardless of how the
    // query was phrased — falls back to an ordinary catalog search instead.
    expect($response->json('summary'))->toBeNull();
    expect($response->json('action'))->not->toBe('get_library_summary');
});

test('asking whether a specific available book exists gets a direct grounded answer', function () {
    Book::factory()->create(['title' => 'Clean Architecture', 'author' => 'Robert C. Martin']);
    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'Do you have Clean Architecture available?']);

    $response->assertOk();
    $response->assertJsonPath('action', 'check_availability');
    expect(collect($response->json('books'))->pluck('title'))->toContain('Clean Architecture');
    expect($response->json('message'))->toContain('Clean Architecture');
});
