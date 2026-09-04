<?php

namespace App\Events;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class BookReturned
{
    use Dispatchable;

    public function __construct(
        public Loan $loan,
        public ?User $actor,
    ) {}
}
