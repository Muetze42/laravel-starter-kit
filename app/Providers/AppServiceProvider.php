<?php

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
        $this->configureCommands();
        $this->configureDevAlwaysToMail();
        $this->configureModels();
        $this->configureRateLimiter();

        $this->definingDefaultPasswordRules();

        // \Illuminate\Support\Facades\Date::use(\Carbon\CarbonImmutable::class);
    }

    /**
     * Configure the application's commands.
     */
    protected function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
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

    /**
     * Configure the application's models.
     */
    protected function configureModels(): void
    {
        // Model::automaticallyEagerLoadRelationships();
        Model::shouldBeStrict(! $this->app->isProduction());
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
     * Specify the default validation rules for passwords.
     */
    protected function definingDefaultPasswordRules(): void
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
}
