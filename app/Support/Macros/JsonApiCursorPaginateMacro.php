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
     * @return Closure(?int, array<int, string>, string, Cursor|string|null): CursorPaginator<int, Model>
     *
     * @noinspection PhpVariableIsUsedOnlyInClosureInspection
     */
    public function __invoke(): Closure
    {
        $macro = $this;

        /**
         * @param  list<string>  $columns
         * @return CursorPaginator<int, Model>
         */
        return function (
            ?int $perPage = null,
            array $columns = ['*'],
            string $pageName = 'page',
            Cursor|string|null $cursor = null,
        ) use ($macro): CursorPaginator {
            $pageSize = $macro->pageSizeParameter($pageName);
            /** @var array<int, string> $columns */
            $paginator = $this->cursorPaginate(
                $perPage ?? $pageSize,
                $columns,
                $pageName . '[cursor]',
                $cursor ?? $macro->cursorParameter($pageName),
            );

            if ($pageSize !== null) {
                return $paginator->appends([$pageName . '[size]' => $pageSize]);
            }

            return $paginator;
        };
    }

    /**
     * Read a valid JSON:API page size query parameter.
     */
    public function pageSizeParameter(string $pageName = 'page'): ?int
    {
        $pageSize = $this->pageParameter('size', $pageName);

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
    public function cursorParameter(string $pageName = 'page'): ?string
    {
        $cursor = $this->pageParameter('cursor', $pageName);

        if (is_string($cursor)) {
            return $cursor;
        }

        return null;
    }

    /**
     * Read a JSON:API page query parameter.
     */
    public function pageParameter(string $key, string $pageName = 'page'): int|string|null
    {
        $pageParameters = request()->query($pageName, []);

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
