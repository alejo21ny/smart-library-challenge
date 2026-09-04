<?php

namespace App\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Providers\NullAiProvider;
use App\AI\Providers\OpenAICompatibleAiProvider;
use App\Listeners\RecordAuditEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            $provider = config('services.ai.provider', 'null');

            if ($provider === 'openai_compatible' && filled(config('services.ai.api_key'))) {
                return new OpenAICompatibleAiProvider(
                    baseUrl: config('services.ai.base_url') ?: 'https://api.openai.com/v1',
                    apiKey: config('services.ai.api_key'),
                    model: config('services.ai.model') ?: 'gpt-4o-mini',
                );
            }

            // Default and fallback: the app must work fully without an AI key.
            return new NullAiProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Note: Vite::prefetch() (Breeze's default) is deliberately NOT enabled.
        // It background-loads every other page's JS chunk after each page load —
        // fine behind a real web server, but it floods `php artisan serve` (this
        // project's dev runtime — see README's Sail/Windows notes) with dozens of
        // concurrent requests and made the app measurably slower to interact with
        // in local testing. Not worth it at this app's scale.

        Event::subscribe(RecordAuditEvent::class);

        // Bound the cost/abuse exposure of the AI-backed assistant endpoint.
        RateLimiter::for('assistant', fn ($request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
