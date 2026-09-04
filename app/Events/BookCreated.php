<?php

namespace App\Events;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class BookCreated
{
    use Dispatchable;

    public function __construct(
        public Book $book,
        public ?User $actor,
    ) {}
}
