<?php

use Illuminate\Support\Facades\Route;
use NormanHuth\Library\Http\Controllers\Api\SentryTunnelController;

Route::get('/', fn () => response()->json(['message' => 'It works!']));
Route::post('sentry-tunnel', SentryTunnelController::class);
