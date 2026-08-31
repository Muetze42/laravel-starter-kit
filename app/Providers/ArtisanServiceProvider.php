<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureCommands();
    }

    /**
     * Configure the application's commands.
     */
    protected function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
