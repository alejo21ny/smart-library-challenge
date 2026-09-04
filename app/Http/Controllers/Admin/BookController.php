<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Book\CreateBookAction;
use App\Actions\Book\DeleteBookAction;
use App\Actions\Book\UpdateBookAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Tag;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Book::class);

        $books = Book::query()
            ->withAvailability()
            ->with('tags')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('availability'), function ($query) use ($request) {
                $request->string('availability')->toString() === 'available'
                    ? $query->available()
                    : $query->borrowed();
            })
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Books/Index', [
            'books' => $books,
            'filters' => $request->only(['q', 'availability']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Book::class);

        return Inertia::render('Admin/Books/Form', [
            'book' => null,
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreBookRequest $request, CreateBookAction $action): RedirectResponse
    {
        $book = $action->execute(
            $request->safe()->except('tags'),
            $request->input('tags', []),
            $request->user(),
        );

        return redirect()->route('admin.books.index')->with('success', "\"{$book->title}\" was added to the catalog.");
    }

    public function edit(Book $book): Response
    {
        $this->authorize('update', $book);

        return Inertia::render('Admin/Books/Form', [
            'book' => $book->load('tags'),
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book, UpdateBookAction $action): RedirectResponse
    {
        $action->execute(
            $book,
            $request->safe()->except('tags'),
            $request->input('tags', []),
            $request->user(),
        );

        return redirect()->route('admin.books.index')->with('success', 'Book updated.');
    }

    public function destroy(Book $book, DeleteBookAction $action): RedirectResponse
    {
        $this->authorize('delete', $book);

        try {
            $action->execute($book, request()->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.books.index')->with('success', 'Book removed from the catalog.');
    }
}
