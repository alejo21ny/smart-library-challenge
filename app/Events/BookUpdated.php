<?php

namespace App\Events;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class BookUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $changes  the attributes that actually changed (new values)
     */
    public function __construct(
        public Book $book,
        public ?User $actor,
        public array $changes = [],
    ) {}
}
