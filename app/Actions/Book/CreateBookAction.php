<?php

namespace App\Actions\Book;

use App\Events\BookCreated;
use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBookAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $tagNames
     */
    public function execute(array $data, array $tagNames, ?User $actor): Book
    {
        return DB::transaction(function () use ($data, $tagNames, $actor) {
            $book = Book::query()->create($data);

            if ($tagNames !== []) {
                $tagIds = collect($tagNames)
                    ->map(fn (string $name) => Tag::query()->firstOrCreate(['name' => trim($name)])->id);

                $book->tags()->sync($tagIds);
            }

            BookCreated::dispatch($book, $actor);

            return $book->fresh(['tags']);
        });
    }
}
