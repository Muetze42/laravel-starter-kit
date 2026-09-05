<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMail();
        $this->configureModels();
        $this->configurePasswordRules();
        $this->prohibitDestructiveCommands();

        // \Illuminate\Support\Facades\Date::use(\Carbon\CarbonImmutable::class);
    }

    /**
     * Configure the application's mail settings.
     */
    protected function configureMail(): void
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

    /**
     * Configure the application's models.
     */
    protected function configureModels(): void
    {
        Model::automaticallyEagerLoadRelationships($this->app->isProduction());
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Specify the default validation rules for passwords.
     */
    protected function configurePasswordRules(): void
    {
        Password::defaults(static function () {
            return Password::min(12)
                // ->uncompromised()
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }

    /**
     * Prohibit destructive database commands in production.
     */
    protected function prohibitDestructiveCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
