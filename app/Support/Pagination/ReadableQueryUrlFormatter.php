<?php

declare(strict_types=1);

namespace App\Support\Pagination;

class ReadableQueryUrlFormatter
{
    /**
     * Format a paginator URL for readable query output.
     */
    public function format(string $url, bool $decode): string
    {
        return $decode ? rawurldecode($url) : $url;
    }
}
