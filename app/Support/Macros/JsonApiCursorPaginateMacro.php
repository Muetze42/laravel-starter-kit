<?php

declare(strict_types=1);

namespace App\Support\Macros;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
 * @mixin \Illuminate\Database\Query\Builder
 */
class JsonApiCursorPaginateMacro
{
    /**
     * Create the JSON:API cursor pagination macro closure.
     *
     * @return Closure(?int, array<int, string>, Cursor|string|null): CursorPaginator<int, Model>
     */
    public function __invoke(): Closure
    {
        $macro = $this;

        /**
         * @param  list<string>  $columns
         * @return CursorPaginator<int, Model>
         */
        $jsonApiCursorPaginate = function (
            ?int $perPage = null,
            array $columns = ['*'],
            Cursor|string|null $cursor = null,
        ) use ($macro): CursorPaginator {
            $pageSize = $macro->pageSizeParameter();
            /** @var array<int, string> $columns */
            $paginator = $this->cursorPaginate(
                $perPage ?? $pageSize,
                $columns,
                'page[cursor]',
                $cursor ?? $macro->cursorParameter(),
            );

            if ($pageSize !== null) {
                return $paginator->appends(['page[size]' => $pageSize]);
            }

            return $paginator;
        };

        return $jsonApiCursorPaginate;
    }

    /**
     * Read a valid JSON:API page size query parameter.
     */
    public function pageSizeParameter(): ?int
    {
        $pageSize = $this->pageParameter('size');

        if (filter_var($pageSize, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $pageSize = (int) $pageSize;

        if ($pageSize < 1) {
            return null;
        }

        return $pageSize;
    }

    /**
     * Read a JSON:API cursor query parameter.
     */
    public function cursorParameter(): ?string
    {
        $cursor = $this->pageParameter('cursor');

        if (is_string($cursor)) {
            return $cursor;
        }

        return null;
    }

    /**
     * Read a JSON:API page query parameter.
     */
    public function pageParameter(string $key): int|string|null
    {
        $pageParameters = request()->query('page', []);

        if (! is_array($pageParameters)) {
            return null;
        }

        $value = $pageParameters[$key] ?? null;

        if (is_int($value) || is_string($value)) {
            return $value;
        }

        return null;
    }
}
