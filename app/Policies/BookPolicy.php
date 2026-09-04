<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Book $book): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Book $book): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->isStaff();
    }
}
