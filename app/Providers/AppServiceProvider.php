<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Foundation\MacroRegistryService;
use App\Support\Macros\JsonApiCursorPaginateMacro;
use App\Support\Macros\JsonApiPaginateMacro;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
    public function boot(MacroRegistryService $macroRegistryService): void
    {
        $this->configureMail();
        $this->configureMacros($macroRegistryService);
        $this->configureModels();
        $this->configurePasswordRules();
        $this->prohibitDestructiveCommands();

        // \Illuminate\Support\Facades\Date::use(\Carbon\CarbonImmutable::class);
    }

    /**
     * Configure the application's custom macros.
     */
    protected function configureMacros(MacroRegistryService $macroRegistryService): void
    {
        $macroRegistryService->macro(JsonApiCursorPaginateMacro::class, QueryBuilder::class);
        $macroRegistryService->macro(JsonApiCursorPaginateMacro::class, EloquentBuilder::class);
        $macroRegistryService->macro(JsonApiPaginateMacro::class, QueryBuilder::class);
        $macroRegistryService->macro(JsonApiPaginateMacro::class, EloquentBuilder::class);
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
