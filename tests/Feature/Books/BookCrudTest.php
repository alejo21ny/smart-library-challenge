<?php

use App\Models\Book;
use App\Models\User;

test('librarian can create a book with tags', function () {
    $librarian = User::factory()->librarian()->create();

    $response = $this->actingAs($librarian)->post(route('admin.books.store'), [
        'title' => 'Refactoring',
        'author' => 'Martin Fowler',
        'isbn' => '9780134757599',
        'category' => 'Programming',
        'publication_year' => 1999,
        'tags' => ['clean-architecture', 'programming'],
    ]);

    $response->assertRedirect(route('admin.books.index'));
    $this->assertDatabaseHas('books', ['title' => 'Refactoring', 'author' => 'Martin Fowler']);

    $book = Book::query()->where('title', 'Refactoring')->firstOrFail();
    expect($book->tags()->pluck('name')->all())->toEqual(['clean-architecture', 'programming']);
});

test('admin can create a book', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.books.store'), [
        'title' => 'Domain-Driven Design',
        'author' => 'Eric Evans',
        'tags' => [],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('books', ['title' => 'Domain-Driven Design']);
});

test('member cannot create a book', function () {
    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->post(route('admin.books.store'), [
        'title' => 'Should Not Save',
        'author' => 'Nobody',
        'tags' => [],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('books', ['title' => 'Should Not Save']);
});

test('member cannot reach the book management pages at all', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)->get(route('admin.books.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.books.create'))->assertForbidden();
});

test('creating a book requires a title and author', function () {
    $librarian = User::factory()->librarian()->create();

    $response = $this->actingAs($librarian)->post(route('admin.books.store'), [
        'title' => '',
        'author' => '',
    ]);

    $response->assertSessionHasErrors(['title', 'author']);
});

test('librarian can update a book', function () {
    $librarian = User::factory()->librarian()->create();
    $book = Book::factory()->create(['title' => 'Old Title']);

    $response = $this->actingAs($librarian)->put(route('admin.books.update', $book), [
        'title' => 'New Title',
        'author' => $book->author,
        'tags' => [],
    ]);

    $response->assertRedirect(route('admin.books.index'));
    expect($book->fresh()->title)->toBe('New Title');
});

test('librarian can delete a book with no active loan', function () {
    $librarian = User::factory()->librarian()->create();
    $book = Book::factory()->create();

    $response = $this->actingAs($librarian)->delete(route('admin.books.destroy', $book));

    $response->assertRedirect(route('admin.books.index'));
    $this->assertModelMissing($book);
});

test('a borrowed book cannot be deleted', function () {
    $librarian = User::factory()->librarian()->create();
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->post(route('catalog.borrow', $book));

    $response = $this->actingAs($librarian)->delete(route('admin.books.destroy', $book));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertModelExists($book);
});

test('any authenticated user can view a single book', function () {
    $member = User::factory()->member()->create();
    $book = Book::factory()->create();

    $this->actingAs($member)->get(route('catalog.show', $book))->assertOk();
});
