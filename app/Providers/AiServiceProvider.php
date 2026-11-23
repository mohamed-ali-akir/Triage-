<?php

namespace App\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Choose the provider from the configuration (AI_PROVIDER in .env).
     * It is a singleton so that tests can script the fake provider.
     */
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, function (): AiProvider {
            $provider = config('ai.provider');

            return match ($provider) {
                'fake' => new FakeAiProvider,
                default => throw new InvalidArgumentException("Unknown AI provider [{$provider}]."),
            };
        });
    }

    public function boot(): void
    {
        // Shared by every AI job: at most N calls per minute for the whole application.
        RateLimiter::for('ai-calls', fn (): Limit => Limit::perMinute((int) config('ai.rate_limit_per_minute')));
    }
}
