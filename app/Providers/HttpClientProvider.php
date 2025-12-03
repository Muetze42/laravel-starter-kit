<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class HttpClientProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Http::globalOptions([
            'headers' => [
                'User-Agent' => Config::string('app.name') . ' ' . Config::string('app.env'),
                'X-Environment' => Config::string('app.env'),
            ],
        ]);
    }
}
