<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | JSON:API Pagination
    |--------------------------------------------------------------------------
    |
    | These values control the default and maximum page sizes used by API
    | pagination helpers that read JSON:API page query parameters.
    |
    */

    'pagination' => [
        'default_size' => 25,
        'max_size' => 100,
    ],
];
