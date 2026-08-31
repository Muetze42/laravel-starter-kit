<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArtisanServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HttpClientProvider;
use App\Providers\MailServiceProvider;
use App\Providers\MigrationServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    ArtisanServiceProvider::class,
    DatabaseServiceProvider::class,
    EventServiceProvider::class,
    HttpClientProvider::class,
    MailServiceProvider::class,
    MigrationServiceProvider::class,
    RouteServiceProvider::class,
];
