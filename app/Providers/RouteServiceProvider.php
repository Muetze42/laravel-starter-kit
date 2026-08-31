<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureUrlGenerator();
        $this->configureRateLimiter();
    }

    /**
     * Configure the application's Rate Limiter.
     */
    protected function configureRateLimiter(): void
    {
        // \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
        //     return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
        //         ->by($request->user()?->id ?: $request->ip());
        // });
    }

    /**
     * Configure the application's URL Generator.
     */
    protected function configureUrlGenerator(): void
    {
        if (! $this->app->isLocal()) {
            URL::forceScheme('https');
        }
    }
}
