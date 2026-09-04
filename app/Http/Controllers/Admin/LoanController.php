<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Loan\BorrowBookAction;
use App\Exceptions\BookUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Loan::class);

        $status = $request->string('status')->toString();

        $loans = Loan::query()
            ->with(['book', 'user'])
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->when($status === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
            ->orderByDesc('borrowed_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Loans/Index', [
            'loans' => $loans,
            'filters' => $request->only('status'),
            'members' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'availableBooks' => Book::query()->available()->orderBy('title')->get(['id', 'title', 'author']),
        ]);
    }

    public function store(Request $request, BorrowBookAction $action): RedirectResponse
    {
        $this->authorize('viewAny', Loan::class);

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $book = Book::findOrFail($data['book_id']);
        $borrower = User::findOrFail($data['user_id']);

        try {
            $action->execute($book, $borrower, $request->user());
        } catch (BookUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "\"{$book->title}\" checked out to {$borrower->name}.");
    }
}
