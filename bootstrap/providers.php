<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FrontendServiceProvider;
use App\Providers\HttpClientProvider;
use App\Providers\MailServiceProvider;
use App\Providers\MigrationServiceProvider;

return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    EventServiceProvider::class,
    FrontendServiceProvider::class,
    HttpClientProvider::class,
    MailServiceProvider::class,
    MigrationServiceProvider::class,
];
