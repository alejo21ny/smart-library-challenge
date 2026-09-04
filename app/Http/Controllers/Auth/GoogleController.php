<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleSocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Optional bonus SSO. If GOOGLE_CLIENT_ID/SECRET aren't configured, these
 * routes simply aren't linked from the UI — the demo login always works
 * regardless (see database/seeders/DemoSeeder.php).
 */
class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        /** @var GoogleSocialiteUser $googleUser */
        $googleUser = $driver->stateless()->user();

        $googleEmailVerified = $this->googleEmailIsVerified($googleUser);

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            // Only trust Google's verification for the exact account being
            // linked, only if Google actually reports it as verified, and
            // only to fill in a still-unverified account — never downgrade
            // or touch an already-verified one. email_verified_at is not
            // fillable (by design, so it's never settable from ordinary
            // request input) — forceFill is the deliberate, narrow exception
            // for this system-computed value.
            if (
                $user->email_verified_at === null
                && $googleEmailVerified
                && $googleUser->getEmail() === $user->email
            ) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } else {
            // Self-registration via Google — same rule as the manual registration
            // form: always MEMBER, never a privileged role. forceCreate (not
            // create) because email_verified_at isn't fillable — it must never
            // be settable from ordinary request input, only from this verified,
            // server-checked Google claim.
            $user = User::query()->forceCreate([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Library Member',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Str::password(32),
                'role' => UserRole::Member,
                'email_verified_at' => $googleEmailVerified ? now() : null,
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Google's OIDC userinfo response includes `email_verified` as a real
     * boolean claim (Socialite's GoogleProvider also copies it to
     * `verified_email` in the raw payload, for backwards compatibility —
     * same value, either key works). Read it directly from the raw payload
     * rather than assuming Google always verifies emails. A strict `=== true`
     * check means anything missing, null, or not literally `true` — e.g. an
     * unverified Google Workspace alias — is treated as unverified.
     */
    private function googleEmailIsVerified(GoogleSocialiteUser $googleUser): bool
    {
        return ($googleUser->getRaw()['email_verified'] ?? null) === true;
    }
}
