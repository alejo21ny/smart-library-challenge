<?php

use App\Models\Book;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->member()->create();
});

test('search by title returns matching books', function () {
    Book::factory()->create(['title' => 'Laravel: Up & Running', 'author' => 'Matt Stauffer']);
    Book::factory()->create(['title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald']);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['q' => 'Laravel']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.title', 'Laravel: Up & Running')
        );
});

test('search by author returns matching books', function () {
    Book::factory()->create(['title' => 'Clean Code', 'author' => 'Robert C. Martin']);
    Book::factory()->create(['title' => 'Some Other Book', 'author' => 'Nobody Special']);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['author' => 'Martin']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.title', 'Clean Code')
        );
});

test('search by isbn returns the exact matching book', function () {
    $match = Book::factory()->create(['isbn' => '9781234567897']);
    Book::factory()->create(['isbn' => '9789999999999']);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['isbn' => '9781234567897']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $match->id)
        );
});

test('availability filter returns only available books', function () {
    $available = Book::factory()->create();
    $borrowed = Book::factory()->create();
    $this->user->loans()->create([
        'book_id' => $borrowed->id,
        'borrowed_at' => now(),
        'due_at' => now()->addDays(14),
    ]);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['availability' => 'available']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $available->id)
            ->where('books.data.0.availability', 'available')
        );
});

test('a search with no matches returns an empty result, not an error', function () {
    Book::factory()->create(['title' => 'Something Else']);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['q' => 'zzz-no-such-book-zzz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('books.data', 0));
});

test('category filter narrows results', function () {
    $matching = Book::factory()->create(['category' => 'Programming']);
    Book::factory()->create(['category' => 'Fiction']);

    $this->actingAs($this->user)
        ->get(route('catalog.index', ['category' => 'Programming']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.id', $matching->id)
        );
});
