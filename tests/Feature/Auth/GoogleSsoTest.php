<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

function fakeSocialiteUser(string $id, string $email, string $name = 'A Reviewer'): SocialiteUserContract
{
    $socialiteUser = Mockery::mock(SocialiteUserContract::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);

    return $socialiteUser;
}

test('the Google sign-in button is only exposed when Google OAuth is actually configured', function () {
    config(['services.google.client_id' => null]);
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('googleOAuthEnabled', false));

    config(['services.google.client_id' => 'configured-client-id']);
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('googleOAuthEnabled', true));
});

test('the Google OAuth routes fail safely (404, not a broken redirect) when unconfigured', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
});

test('a brand-new Google sign-in always creates a MEMBER, never an elevated role', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-999', 'new-reviewer@example.test'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new-reviewer@example.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::Member);
    expect($user->google_id)->toBe('google-999');
    $this->assertAuthenticatedAs($user);
});

test('an existing user signing in with Google gets linked by email, not duplicated', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $existing = User::factory()->member()->create(['email' => 'already-here@example.test', 'google_id' => null]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-123', 'already-here@example.test'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'already-here@example.test')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing->fresh());
    expect($existing->fresh()->google_id)->toBe('google-123');
});

test('a returning Google user is recognized by their google_id even if their email changed', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $existing = User::factory()->member()->create(['google_id' => 'google-777']);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-777', 'a-different-address@example.test'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($existing->fresh());
    expect(User::query()->count())->toBe(1);
});
