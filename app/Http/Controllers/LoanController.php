<?php

namespace App\Http\Controllers;

use App\Actions\Loan\BorrowBookAction;
use App\Actions\Loan\ReturnBookAction;
use App\Exceptions\BookUnavailableException;
use App\Models\Book;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function myLoans(Request $request): Response
    {
        $loans = $request->user()
            ->loans()
            ->with('book')
            ->orderByDesc('borrowed_at')
            ->paginate(10);

        return Inertia::render('MyLoans/Index', [
            'loans' => $loans,
        ]);
    }

    public function borrow(Request $request, Book $book, BorrowBookAction $action): RedirectResponse
    {
        $this->authorize('borrowSelf', Loan::class);

        try {
            $action->execute($book, $request->user(), $request->user());
        } catch (BookUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "You borrowed \"{$book->title}\". Due back in ".config('library.loan_period_days').' days.');
    }

    public function returnBook(Request $request, Loan $loan, ReturnBookAction $action): RedirectResponse
    {
        $this->authorize('returnLoan', $loan);

        $action->execute($loan, $request->user());

        return back()->with('success', 'Book returned. Thanks!');
    }
}
