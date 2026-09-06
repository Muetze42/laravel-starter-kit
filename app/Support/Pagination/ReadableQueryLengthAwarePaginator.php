<?php

declare(strict_types=1);

namespace App\Support\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Override;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends LengthAwarePaginator<TKey, TValue>
 */
class ReadableQueryLengthAwarePaginator extends LengthAwarePaginator
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
     * Get the URL for a given page number.
     *
     * @param  int  $page
     */
    #[Override]
    public function url($page): string
    {
        return new ReadableQueryUrlFormatter()->format(
            parent::url($page),
            $this->decodeQueryUrls,
        );
    }
}
