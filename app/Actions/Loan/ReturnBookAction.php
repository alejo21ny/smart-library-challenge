<?php

namespace App\Actions\Loan;

use App\Events\BookReturned;
use App\Models\Loan;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReturnBookAction
{
    public function execute(Loan $loan, ?User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor) {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($locked->returned_at !== null) {
                throw new DomainException('This loan has already been returned.');
            }

            $locked->update(['returned_at' => now()]);

            BookReturned::dispatch($locked, $actor);

            return $locked;
        });
    }
}
