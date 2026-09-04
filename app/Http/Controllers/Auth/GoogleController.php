<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
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
        $googleUser = $driver->stateless()->user();

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            // Self-registration via Google — same rule as the manual registration
            // form: always MEMBER, never a privileged role.
            $user = User::query()->create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Library Member',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Str::password(32),
                'role' => UserRole::Member,
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
