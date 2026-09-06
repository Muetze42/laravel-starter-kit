<?php

declare(strict_types=1);

namespace App\Support\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Override;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends CursorPaginator<TKey, TValue>
 */
class ReadableQueryCursorPaginator extends CursorPaginator
{
    /**
     * Whether generated pagination URLs should be decoded.
     */
    protected bool $decodeQueryUrls = false;

    /**
     * Configure readable query output for generated URLs.
     */
    public function withReadableQueryUrls(bool $decode = true): static
    {
        $this->decodeQueryUrls = $decode;

        return $this;
    }

    /**
     * Get the URL for a given cursor.
     *
     * @param  Cursor|null  $cursor
     */
    #[Override]
    public function url($cursor): string
    {
        return new ReadableQueryUrlFormatter()->format(
            parent::url($cursor),
            $this->decodeQueryUrls,
        );
    }
}
