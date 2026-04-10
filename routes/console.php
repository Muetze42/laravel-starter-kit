<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// \Illuminate\Support\Facades\Schedule::command('model:prune')->daily();
// \Illuminate\Support\Facades\Schedule::command('auth:clear-resets')->everyFifteenMinutes();
// \Illuminate\Support\Facades\Schedule::command('telescope:prune')->daily();
// \Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
// \Illuminate\Support\Facades\Schedule::command('passport:purge')->hourly();
// \Illuminate\Support\Facades\Schedule::command('horizon:snapshot')->everyFiveMinutes();
// \Illuminate\Support\Facades\Schedule::command('queue:prune-batches --hours=48 --unfinished=72')->daily();
// \Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=48')->daily();
