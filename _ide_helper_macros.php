<?php

/** @noinspection AutoloadingIssuesInspection */

// phpcs:ignoreFile

declare(strict_types=1);

namespace Illuminate\Pagination;

/**
 * @template TKey of array-key
 * @template TValue
 */
class LengthAwarePaginator
{
    /**
     * Configure readable query output for generated URLs.
     *
     * @see \App\Support\Pagination\ReadableQueryLengthAwarePaginator::withReadableQueryUrls()
     */
    public function withReadableQueryUrls(bool $decode = true): static {}
}

/**
 * @template TKey of array-key
 * @template TValue
 */
class CursorPaginator
{
    /**
     * Configure readable query output for generated URLs.
     *
     * @see \App\Support\Pagination\ReadableQueryCursorPaginator::withReadableQueryUrls()
     */
    public function withReadableQueryUrls(bool $decode = true): static {}
}
