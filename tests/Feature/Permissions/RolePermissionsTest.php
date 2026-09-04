<?php

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

test('member permissions', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->get(route('catalog.index'))->assertOk();
    $this->actingAs($member)->get(route('catalog.show', $book))->assertOk();
    $this->actingAs($member)->get(route('loans.mine'))->assertOk();
    $this->actingAs($member)->post(route('catalog.borrow', $book))->assertRedirect();

    // Not staff:
    $this->actingAs($member)->get(route('admin.books.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.loans.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.books.store'), ['title' => 'x', 'author' => 'y'])->assertForbidden();
});

test('librarian permissions', function () {
    $librarian = User::factory()->librarian()->create();
    $book = Book::factory()->create();

    $this->actingAs($librarian)->get(route('catalog.index'))->assertOk();
    $this->actingAs($librarian)->get(route('admin.books.index'))->assertOk();
    $this->actingAs($librarian)->get(route('admin.books.create'))->assertOk();
    $this->actingAs($librarian)->get(route('admin.loans.index'))->assertOk();
    $this->actingAs($librarian)
        ->post(route('admin.books.store'), ['title' => 'New Book', 'author' => 'Someone', 'tags' => []])
        ->assertRedirect();

    // Not admin:
    $this->actingAs($librarian)->get(route('admin.users.index'))->assertForbidden();
});

test('admin permissions', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->member()->create();

    $this->actingAs($admin)->get(route('admin.books.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.loans.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($admin)
        ->patch(route('admin.users.role', $otherUser), ['role' => 'librarian'])
        ->assertRedirect();

    expect($otherUser->fresh()->role->value)->toBe('librarian');
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.role', $admin), ['role' => 'member'])->assertForbidden();
    expect($admin->fresh()->role->value)->toBe('admin');
});

test('guests are redirected to login for any protected route', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('catalog.index'))->assertRedirect(route('login'));
});

test('a member cannot view another member\'s single loan', function () {
    $owner = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    $book = Book::factory()->create();
    $this->actingAs($owner)->post(route('catalog.borrow', $book));
    $loan = Loan::query()->where('book_id', $book->id)->firstOrFail();

    expect($other->can('view', $loan))->toBeFalse();
    expect($owner->can('view', $loan))->toBeTrue();
});
