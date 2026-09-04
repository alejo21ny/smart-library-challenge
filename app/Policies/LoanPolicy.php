<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /** View the all-loans / circulation list. */
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Loan $loan): bool
    {
        return $loan->user_id === $user->id || $user->isStaff();
    }

    /** Borrow a book for oneself — any authenticated user. */
    public function borrowSelf(User $user): bool
    {
        return true;
    }

    public function returnLoan(User $user, Loan $loan): bool
    {
        return $loan->user_id === $user->id || $user->isStaff();
    }
}
