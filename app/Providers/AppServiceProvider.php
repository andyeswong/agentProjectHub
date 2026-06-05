<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');

            $limit = $apiKey?->rate_limit ?? 120;
            $key   = $apiKey?->id ?? $request->ip();

            return Limit::perMinute($limit)->by($key);
        });

        // Tighter, separate bucket for sensitive reads (secret reveal + memory
        // search) so a compromised key is rate-capped on exfiltration regardless
        // of its generous global limit.
        RateLimiter::for('sensitive', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');

            $limit = (int) config('security.sensitive_rate_limit', 30);
            $key   = ($apiKey?->id ?? $request->ip()) . ':sensitive';

            return Limit::perMinute($limit)->by($key);
        });
    }
}
