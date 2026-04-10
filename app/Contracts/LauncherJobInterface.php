<?php

declare(strict_types=1);

namespace App\Contracts;

interface LauncherJobInterface
{
    /**
     * The console command description.
     */
    public static function description(): string;
}
