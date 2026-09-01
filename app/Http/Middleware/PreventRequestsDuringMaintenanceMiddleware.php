<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\MaintenanceException;
use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Override;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PreventRequestsDuringMaintenanceMiddleware extends PreventRequestsDuringMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \ErrorException
     */
    #[Override]
    public function handle($request, Closure $next): mixed
    {
        try {
            return parent::handle($request, $next);
        } catch (HttpException $httpException) {
            throw new MaintenanceException(
                $httpException->getStatusCode(),
                'Service Unavailable',
                $httpException,
                $httpException->getHeaders()
            );
        }
    }
}
