<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class HttpClientServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureGlobalOptions();
        $this->preventStrayRequests();
    }

    /**
     * Configure global HTTP client options.
     */
    protected function configureGlobalOptions(): void
    {
        Http::globalOptions([
            'headers' => [
                'User-Agent' => Config::string('app.name'),
                'X-Environment' => Config::string('app.env'),
            ],
        ]);
    }

    /**
     * Prevents stray requests during unit tests.
     */
    protected function preventStrayRequests(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        Http::preventStrayRequests();
    }
}
