<?php

namespace App\Actions\Book;

use App\Events\BookUpdated;
use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBookAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $tagNames
     */
    public function execute(Book $book, array $data, array $tagNames, ?User $actor): Book
    {
        return DB::transaction(function () use ($book, $data, $tagNames, $actor) {
            $book->fill($data);
            $changes = $book->getDirty();
            $book->save();

            $tagIds = collect($tagNames)
                ->map(fn (string $name) => Tag::query()->firstOrCreate(['name' => trim($name)])->id);
            $book->tags()->sync($tagIds);

            if ($changes !== []) {
                BookUpdated::dispatch($book, $actor, $changes);
            }

            return $book->fresh(['tags']);
        });
    }
}
