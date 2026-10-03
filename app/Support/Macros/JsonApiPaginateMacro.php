<?php

declare(strict_types=1);

namespace App\Support\Macros;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
 * @mixin \Illuminate\Database\Query\Builder
 */
class JsonApiPaginateMacro
{
    /**
     * Create the JSON:API pagination macro closure.
     *
     * @return Closure(?int, array<int, string>, string, ?int, Closure|int|null): LengthAwarePaginator<int, Model>
     *
     * @noinspection PhpVariableIsUsedOnlyInClosureInspection
     */
    public function __invoke(): Closure
    {
        $macro = $this;

        /**
         * @param  list<string>  $columns
         * @return LengthAwarePaginator<int, Model>
         */
        return function (
            ?int $perPage = null,
            array $columns = ['*'],
            string $pageName = 'page',
            ?int $page = null,
            Closure|int|null $total = null
        ) use ($macro): LengthAwarePaginator {
            $pageSize = $macro->pageSizeParameter($pageName);
            /** @var array<int, string> $columns */
            $paginator = $this->paginate(
                $perPage ?? $pageSize,
                $columns,
                $pageName . '[number]',
                $page ?? $macro->pageNumberParameter($pageName),
                $total,
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
     * Read a valid JSON:API page number query parameter.
     */
    public function pageNumberParameter(string $pageName = 'page'): ?int
    {
        $pageNumber = $this->pageParameter('number', $pageName);

        if (filter_var($pageNumber, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return (int) $pageNumber;
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
