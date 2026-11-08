<?php

declare(strict_types=1);

use App\Http\Middleware\ForceJson;
use App\Http\Middleware\LowercaseEmailAddresses;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as OriginalPreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['api', ForceJson::class])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LowercaseEmailAddresses::class);
        $middleware->replace(
            OriginalPreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $exceptions->shouldRenderJsonWhen(function (Request $request): bool {
            return $request->expectsJson() || $request->is('api') || $request->is('api/*');
        });
    })->create();
