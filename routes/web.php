<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\HealthCheck;
use Illuminate\Support\Facades\Route;

Route::get('/', HealthCheck::class);
