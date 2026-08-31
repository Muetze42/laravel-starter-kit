<?php

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
