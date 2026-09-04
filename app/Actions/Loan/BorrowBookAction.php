<?php

namespace App\Actions\Loan;

use App\Events\BookBorrowed;
use App\Exceptions\BookUnavailableException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Two independent layers of concurrency safety, deliberately:
 *
 * 1. `lockForUpdate()` on the book row inside a transaction serializes
 *    concurrent borrow attempts for the SAME book against each other — the
 *    second request's exists() check runs only after the first has
 *    committed or rolled back, so it sees the true, current state.
 * 2. The database's own unique partial index (`loans_book_id_active_unique`,
 *    see the loans migration) makes it structurally impossible to insert a
 *    second active loan for a book even if #1 were ever bypassed. The
 *    catch below exists specifically to turn that low-level guarantee into
 *    a clean domain exception rather than a raw SQL error leaking out.
 */
class BorrowBookAction
{
    public function execute(Book $book, User $borrower, ?User $actor): Loan
    {
        return DB::transaction(function () use ($book, $borrower, $actor) {
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);

            $hasActiveLoan = Loan::query()
                ->where('book_id', $locked->id)
                ->whereNull('returned_at')
                ->exists();

            if ($hasActiveLoan) {
                throw new BookUnavailableException;
            }

            $now = now();

            try {
                $loan = Loan::query()->create([
                    'book_id' => $locked->id,
                    'user_id' => $borrower->id,
                    'borrowed_at' => $now,
                    'due_at' => $now->copy()->addDays((int) config('library.loan_period_days')),
                ]);
            } catch (QueryException $e) {
                // SQLSTATE 23505 = unique_violation. Belt-and-suspenders: the
                // lock above should make this unreachable, but if it's ever
                // hit, surface the same clean domain exception, not raw SQL.
                if ($e->getCode() === '23505') {
                    throw new BookUnavailableException;
                }

                throw $e;
            }

            BookBorrowed::dispatch($loan, $actor);

            return $loan;
        });
    }
}
