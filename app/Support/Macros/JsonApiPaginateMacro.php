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
     * @return Closure(?int, array<int, string>, ?int, Closure|int|null): LengthAwarePaginator<int, Model>
     */
    public function __invoke(): Closure
    {
        $macro = $this;

        /**
         * @param  list<string>  $columns
         * @return LengthAwarePaginator<int, Model>
         */
        $jsonApiPaginate = function (
            ?int $perPage = null,
            array $columns = ['*'],
            ?int $page = null,
            Closure|int|null $total = null
        ) use ($macro): LengthAwarePaginator {
            $pageSize = $macro->pageSizeParameter();
            /** @var array<int, string> $columns */
            $paginator = $this->paginate(
                $perPage ?? $pageSize,
                $columns,
                'page[number]',
                $page ?? $macro->pageNumberParameter(),
                $total,
            );

            if ($pageSize !== null) {
                return $paginator->appends(['page[size]' => $pageSize]);
            }

            return $paginator;
        };

        return $jsonApiPaginate;
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
     * Read a valid JSON:API page number query parameter.
     */
    public function pageNumberParameter(): ?int
    {
        $pageNumber = $this->pageParameter('number');

        if (filter_var($pageNumber, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return (int) $pageNumber;
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
