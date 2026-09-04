<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleSocialiteUser;

function fakeSocialiteUser(string $id, string $email, string $name = 'A Reviewer', bool $emailVerified = true): GoogleSocialiteUser
{
    $socialiteUser = Mockery::mock(GoogleSocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getNickname')->andReturn(null);
    // Mirrors what Socialite's real GoogleProvider::mapUserToObject() puts in
    // the raw payload from Google's OIDC userinfo response.
    $socialiteUser->shouldReceive('getRaw')->andReturn(['email_verified' => $emailVerified]);

    return $socialiteUser;
}

function fakeGoogleDriver(GoogleSocialiteUser $socialiteUser): void
{
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
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

    fakeGoogleDriver(fakeSocialiteUser('google-999', 'new-reviewer@example.test'));

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new-reviewer@example.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::Member);
    expect($user->google_id)->toBe('google-999');
    $this->assertAuthenticatedAs($user);
});

test('an existing user signing in with Google gets linked by email, not duplicated', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $existing = User::factory()->member()->create(['email' => 'already-here@example.test', 'google_id' => null]);

    fakeGoogleDriver(fakeSocialiteUser('google-123', 'already-here@example.test'));

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'already-here@example.test')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing->fresh());
    expect($existing->fresh()->google_id)->toBe('google-123');
});

test('a returning Google user is recognized by their google_id even if their email changed', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $existing = User::factory()->member()->create([
        'google_id' => 'google-777',
        'email_verified_at' => null,
    ]);

    fakeGoogleDriver(fakeSocialiteUser('google-777', 'a-different-address@example.test', emailVerified: true));

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($existing->fresh());
    expect(User::query()->count())->toBe(1);
    // Google's reported email no longer matches the stored account's email —
    // even though Google says *that* address is verified, it must not stamp
    // verification onto a different, unconfirmed email address.
    expect($existing->fresh()->email_verified_at)->toBeNull();
});

test('a new Google user whose email Google reports as verified is marked verified', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    fakeGoogleDriver(fakeSocialiteUser('google-201', 'verified-person@example.test', emailVerified: true));

    $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'verified-person@example.test')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();
});

test('a verified Google user lands past the email-verification wall, not stuck on it', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    fakeGoogleDriver(fakeSocialiteUser('google-202', 'verified-person@example.test', emailVerified: true));

    $this->get(route('auth.google.callback'));

    // The verified middleware guards this whole route group — reaching it
    // without a redirect to /verify-email is the real proof.
    $this->get(route('dashboard'))->assertOk();
});

test('a new Google user whose email Google reports as unverified is not marked verified', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    fakeGoogleDriver(fakeSocialiteUser('google-203', 'unverified-person@example.test', emailVerified: false));

    $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'unverified-person@example.test')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    // Confirmed blocked by the real middleware, not just an empty column.
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('linking an existing unverified account only stamps verification when Google confirms it for that exact email', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $existing = User::factory()->member()->create([
        'email' => 'was-unverified@example.test',
        'google_id' => null,
        'email_verified_at' => null,
    ]);

    fakeGoogleDriver(fakeSocialiteUser('google-301', 'was-unverified@example.test', emailVerified: true));

    $this->get(route('auth.google.callback'));

    expect($existing->fresh()->email_verified_at)->not->toBeNull();
});

test('an existing user role is never changed by linking a Google account', function () {
    config(['services.google.client_id' => 'configured-client-id']);

    $librarian = User::factory()->librarian()->create(['email' => 'librarian@example.test', 'google_id' => null]);

    fakeGoogleDriver(fakeSocialiteUser('google-401', 'librarian@example.test'));

    $this->get(route('auth.google.callback'));

    expect($librarian->fresh()->role)->toBe(UserRole::Librarian);
});
