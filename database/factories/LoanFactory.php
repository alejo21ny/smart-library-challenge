<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        $borrowedAt = fake()->dateTimeBetween('-60 days', '-1 days');

        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'borrowed_at' => $borrowedAt,
            'due_at' => (clone $borrowedAt)->modify('+'.config('library.loan_period_days').' days'),
            'returned_at' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(function (array $attributes) {
            $borrowedAt = $attributes['borrowed_at'];

            return [
                'returned_at' => fake()->dateTimeBetween($borrowedAt, 'now'),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'borrowed_at' => now()->subDays(30),
            'due_at' => now()->subDays(16),
            'returned_at' => null,
        ]);
    }
}
