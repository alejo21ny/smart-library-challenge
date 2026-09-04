<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class BookDeleted
{
    use Dispatchable;

    public function __construct(
        public int $bookId,
        public string $title,
        public ?User $actor,
    ) {}
}
