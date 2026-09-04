<?php

namespace App\Actions\Book;

use App\Events\BookDeleted;
use App\Models\Book;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeleteBookAction
{
    public function execute(Book $book, ?User $actor): void
    {
        DB::transaction(function () use ($book, $actor) {
            if ($book->loadMissing('activeLoan')->activeLoan !== null) {
                throw new DomainException('This book is currently borrowed and cannot be deleted.');
            }

            $id = $book->id;
            $title = $book->title;

            $book->tags()->detach();
            $book->delete();

            BookDeleted::dispatch($id, $title, $actor);
        });
    }
}
