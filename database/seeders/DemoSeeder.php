<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic but CLEARLY demo data — one account per role (documented in
 * README.md) plus a small, varied catalog so every UI state (available,
 * borrowed, overdue, empty search) is actually reachable during review.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Ada Admin', 'role' => 'admin', 'password' => 'password', 'email_verified_at' => now()]
        );

        $librarian = User::query()->updateOrCreate(
            ['email' => 'librarian@example.test'],
            ['name' => 'Lee Librarian', 'role' => 'librarian', 'password' => 'password', 'email_verified_at' => now()]
        );

        $member = User::query()->updateOrCreate(
            ['email' => 'member@example.test'],
            ['name' => 'Mia Member', 'role' => 'member', 'password' => 'password', 'email_verified_at' => now()]
        );

        $secondMember = User::query()->updateOrCreate(
            ['email' => 'member2@example.test'],
            ['name' => 'Sam Reader', 'role' => 'member', 'password' => 'password', 'email_verified_at' => now()]
        );

        $tagNames = ['php', 'laravel', 'javascript', 'clean-architecture', 'databases', 'cloud', 'beginner-friendly', 'fiction', 'history'];
        $tags = collect($tagNames)->mapWithKeys(fn ($name) => [$name => Tag::query()->firstOrCreate(['name' => $name])]);

        $catalog = [
            ['title' => 'Laravel: Up & Running', 'author' => 'Matt Stauffer', 'category' => 'Programming', 'publication_year' => 2023, 'tags' => ['php', 'laravel', 'beginner-friendly'], 'description' => 'A practical introduction to building applications with the Laravel framework.'],
            ['title' => 'Clean Architecture', 'author' => 'Robert C. Martin', 'category' => 'Architecture', 'publication_year' => 2017, 'tags' => ['clean-architecture'], 'description' => 'A craftsman\'s guide to software structure and design.'],
            ['title' => 'PHP for Beginners', 'author' => 'Wei Zhang', 'category' => 'Programming', 'publication_year' => 2021, 'tags' => ['php', 'beginner-friendly'], 'description' => 'An approachable start to PHP for people new to programming.'],
            ['title' => 'Designing Data-Intensive Applications', 'author' => 'Martin Kleppmann', 'category' => 'Databases', 'publication_year' => 2017, 'tags' => ['databases'], 'description' => 'The big ideas behind reliable, scalable, and maintainable systems.'],
            ['title' => 'Domain-Driven Laravel', 'author' => 'Carlos Mendez', 'category' => 'Architecture', 'publication_year' => 2022, 'tags' => ['php', 'laravel', 'clean-architecture'], 'description' => 'Applying domain-driven design patterns within a Laravel codebase.'],
            ['title' => 'The Cloud Native Handbook', 'author' => 'Priya Nair', 'category' => 'Cloud & DevOps', 'publication_year' => 2024, 'tags' => ['cloud'], 'description' => 'Containers, orchestration, and running real workloads on AWS.'],
            ['title' => 'Modern JavaScript for Newcomers', 'author' => 'Alex Turner', 'category' => 'Programming', 'publication_year' => 2020, 'tags' => ['javascript', 'beginner-friendly'], 'description' => 'A gentle, project-based path into modern JavaScript.'],
            ['title' => 'A History of Medellín', 'author' => 'Isabel Rojas', 'category' => 'History', 'publication_year' => 2015, 'tags' => ['history'], 'description' => 'The story of one of Colombia\'s most transformed cities.'],
            ['title' => 'The Long Return', 'author' => 'Nadia Alvi', 'category' => 'Fiction', 'publication_year' => 2019, 'tags' => ['fiction'], 'description' => 'A novel about migration, memory, and coming home.'],
            ['title' => 'PostgreSQL at Scale', 'author' => 'Tomas Berg', 'category' => 'Databases', 'publication_year' => 2023, 'tags' => ['databases', 'cloud'], 'description' => 'Indexing, partitioning, and operating PostgreSQL under real load.'],
            ['title' => 'Refactoring', 'author' => 'Martin Fowler', 'category' => 'Programming', 'publication_year' => 1999, 'tags' => ['clean-architecture'], 'description' => 'Improving the design of existing code, one small step at a time.'],
            ['title' => 'Beginner\'s Guide to Laravel Testing', 'author' => 'Grace Iyer', 'category' => 'Programming', 'publication_year' => 2024, 'tags' => ['php', 'laravel', 'beginner-friendly'], 'description' => 'Pest, factories, and a practical approach to testing Laravel apps.'],
        ];

        $books = collect($catalog)->map(function (array $entry) use ($tags) {
            $book = Book::query()->updateOrCreate(
                ['title' => $entry['title'], 'author' => $entry['author']],
                [
                    'description' => $entry['description'],
                    'category' => $entry['category'],
                    'publication_year' => $entry['publication_year'],
                ]
            );

            $book->tags()->sync(collect($entry['tags'])->map(fn ($t) => $tags[$t]->id));

            return $book;
        });

        // A mix of loan states so every dashboard/catalog view has something real to show.
        if (! Loan::query()->exists()) {
            Loan::query()->create([
                'book_id' => $books[0]->id, // Laravel: Up & Running -> currently borrowed
                'user_id' => $member->id,
                'borrowed_at' => now()->subDays(3),
                'due_at' => now()->addDays((int) config('library.loan_period_days') - 3),
            ]);

            Loan::query()->create([
                'book_id' => $books[3]->id, // Designing Data-Intensive Applications -> overdue
                'user_id' => $secondMember->id,
                'borrowed_at' => now()->subDays(20),
                'due_at' => now()->subDays(6),
            ]);

            Loan::query()->create([
                'book_id' => $books[6]->id, // Modern JavaScript -> returned, in history
                'user_id' => $member->id,
                'borrowed_at' => now()->subDays(30),
                'due_at' => now()->subDays(16),
                'returned_at' => now()->subDays(18),
            ]);
        }
    }
}
