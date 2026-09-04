<?php

use App\AI\Contracts\AiProviderInterface;
use App\AI\Data\SearchIntentData;
use App\AI\Providers\NullAiProvider;
use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;

test('with no AI provider configured, the assistant still returns real deterministic results', function () {
    // Explicitly bind the Null provider — this is what happens with AI_PROVIDER unset.
    app()->bind(AiProviderInterface::class, fn () => new NullAiProvider);

    $member = User::factory()->member()->create();
    Book::factory()->create(['title' => 'Laravel Essentials', 'author' => 'Someone']);
    Book::factory()->create(['title' => 'Completely Unrelated Novel', 'author' => 'Someone Else']);

    $response = $this->actingAs($member)->postJson(route('assistant.query'), [
        'query' => 'laravel',
    ]);

    $response->assertOk();
    $response->assertJsonPath('degraded', false); // NullAiProvider is not a failure state, it's the default
    $titles = collect($response->json('books'))->pluck('title');
    expect($titles)->toContain('Laravel Essentials');
    expect($titles)->not->toContain('Completely Unrelated Novel');
});

test('the deterministic fallback parses availability and year phrases from the query', function () {
    $provider = new NullAiProvider;

    $intent = $provider->extractSearchIntent('I need an available beginner-friendly Laravel book published after 2020');

    expect($intent->availability)->toBe('available');
    expect($intent->publishedAfter)->toBe(2020);
    expect($intent->keywords)->toContain('laravel');
});

test('a structured intent from a mocked AI provider drives a real catalog query', function () {
    $laravelBook = Book::factory()->create(['title' => 'Deep Laravel', 'author' => 'A. Author', 'publication_year' => 2023]);
    $tag = Tag::query()->create(['name' => 'clean-architecture']);
    $laravelBook->tags()->attach($tag);
    Book::factory()->create(['title' => 'Should Not Match', 'publication_year' => 2010]);

    $mock = Mockery::mock(AiProviderInterface::class);
    $mock->shouldReceive('extractSearchIntent')
        ->once()
        ->andReturn(SearchIntentData::fromArray([
            'tags' => ['clean-architecture'],
            'published_after' => 2020,
        ]));
    $mock->shouldReceive('summarize')->once()->andReturn('This matches your tag and year filters.');
    app()->instance(AiProviderInterface::class, $mock);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), [
        'query' => 'clean architecture book from the last few years',
    ]);

    $response->assertOk();
    $ids = collect($response->json('books'))->pluck('id');
    expect($ids)->toContain($laravelBook->id)->toHaveCount(1);
    expect($response->json('message'))->toBe('This matches your tag and year filters.');
});

test('the assistant never returns a book that does not exist in the catalog', function () {
    // Even if the AI provider's intent extraction is nonsense, only real
    // Eloquent query results can ever come back — there's no path for the
    // provider to inject fabricated book data into the response.
    $real = Book::factory()->create(['title' => 'A Real Book']);

    $mock = Mockery::mock(AiProviderInterface::class);
    $mock->shouldReceive('extractSearchIntent')->once()->andReturn(SearchIntentData::fromArray([]));
    $mock->shouldReceive('summarize')->once()->andReturnUsing(
        fn (string $query, Collection $books) => 'Matched: '.$books->pluck('title')->implode(', ')
    );
    app()->instance(AiProviderInterface::class, $mock);

    $member = User::factory()->member()->create();

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'anything at all']);

    $response->assertOk();
    $titles = collect($response->json('books'))->pluck('title')->all();
    expect($titles)->each->toBeIn(Book::query()->pluck('title')->all());
    expect($titles)->toContain('A Real Book');
});

test('if intent extraction throws, the assistant degrades gracefully instead of failing', function () {
    $mock = Mockery::mock(AiProviderInterface::class);
    $mock->shouldReceive('extractSearchIntent')->once()->andThrow(new RuntimeException('provider down'));
    $mock->shouldReceive('summarize')->once()->andReturn('Fallback summary.');
    app()->instance(AiProviderInterface::class, $mock);

    $member = User::factory()->member()->create();
    Book::factory()->create(['title' => 'Findable Book']);

    $response = $this->actingAs($member)->postJson(route('assistant.query'), ['query' => 'findable']);

    $response->assertOk();
    $response->assertJsonPath('degraded', true);
});

test('assistant query is validated', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)->postJson(route('assistant.query'), ['query' => ''])
        ->assertStatus(422);
});
