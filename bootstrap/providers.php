<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HttpClientServiceProvider;
use App\Providers\MigrationServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    EventServiceProvider::class,
    HttpClientServiceProvider::class,
    MigrationServiceProvider::class,
    RouteServiceProvider::class,
];
