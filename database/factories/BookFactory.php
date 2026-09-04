<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucwords(fake()->words(random_int(2, 5), true)),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->isbn13(),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement([
                'Programming', 'Architecture', 'Databases', 'Cloud & DevOps',
                'Fiction', 'History', 'Science', 'Business',
            ]),
            'publication_year' => fake()->numberBetween(1990, (int) date('Y')),
            'cover_url' => null,
        ];
    }
}
