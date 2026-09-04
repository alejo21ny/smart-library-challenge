<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    private const ALLOWED_SORTS = ['title', 'author', 'publication_year', 'created_at'];

    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString() ?: null;
        $sort = $request->string('sort')->toString() ?: 'title';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $books = Book::query()
            ->withAvailability()
            ->with('tags')
            ->search($q)
            ->category($request->string('category')->toString() ?: null)
            ->tag($request->string('tag')->toString() ?: null)
            ->isbn($request->string('isbn')->toString() ?: null)
            ->when($request->filled('author'), fn ($query) => $query->where('author', 'ilike', '%'.$request->string('author').'%'))
            ->when($request->string('availability')->toString() === 'available', fn ($query) => $query->available())
            ->when($request->string('availability')->toString() === 'borrowed', fn ($query) => $query->borrowed())
            ->when(
                $q === null && in_array($sort, self::ALLOWED_SORTS, true),
                fn ($query) => $query->orderBy($sort, $dir)
            )
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Catalog/Index', [
            'books' => $books,
            'filters' => $request->only(['q', 'category', 'tag', 'isbn', 'author', 'availability', 'sort', 'dir']),
            'categories' => Book::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'tags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function show(Book $book): Response
    {
        $this->authorize('view', $book);

        $book->loadMissing(['tags', 'activeLoan.user']);

        return Inertia::render('Catalog/Show', [
            'book' => $book,
        ]);
    }
}
