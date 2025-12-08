<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $column = 'active_at',
        string $table = 'users',
        string $keyColumn = 'id'
    ): Response {
        if ($user = $request->user()) {
            DB::table($table)
                ->where($keyColumn, $user->getKey())
                ->update([$column => now()]);
        }

        return $next($request);
    }
}
