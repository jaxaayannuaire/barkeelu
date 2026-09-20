<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-token', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });
        RateLimiter::for('webhook-wave', function (Request $request): Limit {
            return Limit::perMinute(120)->by('webhook-wave:'.strtoupper((string) $request->route('provider')).':'.$request->ip());
        });
    }
}
