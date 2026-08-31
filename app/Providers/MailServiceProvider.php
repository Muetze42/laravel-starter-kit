<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureDevAlwaysToMail();
    }

    /**
     * Configure the application's global email receiver for development environment.
     */
    protected function configureDevAlwaysToMail(): void
    {
        if (! $this->app->environment(['local', 'staging'])) {
            return;
        }

        if (! $address = config('mail.always_to')) {
            return;
        }

        if (is_string($address)) {
            Mail::alwaysTo($address);
        }
    }
}
