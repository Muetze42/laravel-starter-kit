<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JsonApiPaginateMacroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Query builder results can be paginated with JSON:API page parameters.
     */
    public function test_query_builder_uses_json_api_page_number(): void
    {
        User::factory()->count(5)->sequence(
            ['name' => 'User 1'],
            ['name' => 'User 2'],
            ['name' => 'User 3'],
            ['name' => 'User 4'],
            ['name' => 'User 5'],
        )->create();
        $this->registerQueryBuilderRoute();

        $response = $this->getJson('/testing/json-api-query-builder?page[number]=2');

        $response->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('data.0.name', 'User 3');
    }

    /**
     * Eloquent builder results do not append implicit page sizes to URLs.
     */
    public function test_eloquent_builder_does_not_append_implicit_page_size_to_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute(2);

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[number]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('last_page', 3);

        $this->assertStringNotContainsString('page%5Bsize%5D=', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page%5Bnumber%5D=2', (string) $response->json('next_page_url'));
    }

    /**
     * Missing explicit page size falls back to Laravel's paginator default.
     */
    public function test_missing_explicit_page_size_uses_laravel_paginator_default(): void
    {
        User::factory()->count(20)->create();
        $this->registerEloquentBuilderRoute();

        $response = $this->getJson('/testing/json-api-eloquent-builder');

        $response->assertOk()
            ->assertJsonPath('per_page', 15);
    }

    /**
     * Request page sizes are passed through to pagination.
     */
    public function test_request_page_size_is_used_for_pagination(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute();

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[size]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page%5Bsize%5D=1', (string) $response->json('next_page_url'));
    }

    /**
     * Default pagination keeps Laravel-encoded query URLs.
     */
    public function test_default_pagination_keeps_encoded_query_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute();

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[size]=1&page[number]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page%5Bsize%5D=1', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page%5Bnumber%5D=2', (string) $response->json('next_page_url'));
    }

    /**
     * Readable pagination decodes generated query URLs.
     */
    public function test_readable_pagination_outputs_readable_query_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute(decodeQueryUrls: true);

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[size]=1&page[number]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page[size]=1', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page[number]=2', (string) $response->json('next_page_url'));
    }

    /**
     * Standard Laravel pagination can decode generated query URLs.
     */
    public function test_standard_pagination_outputs_readable_query_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerStandardPaginationRoute();

        $response = $this->getJson('/testing/standard-pagination?filter[name]=Jane%20Doe&page=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 2);

        $this->assertStringContainsString('filter[name]=Jane Doe', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page=2', (string) $response->json('next_page_url'));
    }

    /**
     * Readable pagination can be explicitly disabled.
     */
    public function test_readable_pagination_can_be_disabled(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute(decodeQueryUrls: false);

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[size]=1&page[number]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page%5Bsize%5D=1', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page%5Bnumber%5D=2', (string) $response->json('next_page_url'));
    }

    /**
     * Invalid request page sizes fall back to Laravel's paginator default.
     */
    public function test_invalid_page_size_uses_laravel_paginator_default(): void
    {
        User::factory()->count(20)->create();
        $this->registerEloquentBuilderRoute();

        $response = $this->getJson('/testing/json-api-eloquent-builder?page[size]=-213');

        $response->assertOk()
            ->assertJsonPath('per_page', 15);

        $this->assertStringNotContainsString('page%5Bsize%5D=-213', (string) $response->json('next_page_url'));
    }

    /**
     * Explicit per-page values are used when the request does not include page size.
     */
    public function test_explicit_per_page_is_used_when_page_size_is_missing(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderRoute(4);

        $response = $this->getJson('/testing/json-api-eloquent-builder');

        $response->assertOk()
            ->assertJsonPath('per_page', 4);
    }

    /**
     * Query builder results can be cursor paginated with JSON:API cursor parameters.
     */
    public function test_query_builder_uses_json_api_cursor_parameter(): void
    {
        User::factory()->count(5)->sequence(
            ['name' => 'User 1'],
            ['name' => 'User 2'],
            ['name' => 'User 3'],
            ['name' => 'User 4'],
            ['name' => 'User 5'],
        )->create();
        $this->registerQueryBuilderCursorRoute();

        $firstResponse = $this->getJson('/testing/json-api-query-builder-cursor');
        $cursor = (string) $firstResponse->json('next_cursor');

        $response = $this->getJson('/testing/json-api-query-builder-cursor?page[cursor]=' . urlencode($cursor));

        $response->assertOk()
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('data.0.name', 'User 3');
    }

    /**
     * Eloquent cursor pagination does not append implicit page sizes to URLs.
     */
    public function test_eloquent_cursor_pagination_does_not_append_implicit_page_size_to_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderCursorRoute(2);

        $response = $this->getJson('/testing/json-api-eloquent-builder-cursor');

        $response->assertOk()
            ->assertJsonPath('per_page', 2);

        $this->assertStringNotContainsString('page%5Bsize%5D=', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page%5Bcursor%5D=', (string) $response->json('next_page_url'));
    }

    /**
     * Request page sizes are passed through to cursor pagination.
     */
    public function test_request_page_size_is_used_for_cursor_pagination(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderCursorRoute();

        $response = $this->getJson('/testing/json-api-eloquent-builder-cursor?page[size]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page%5Bsize%5D=1', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page%5Bcursor%5D=', (string) $response->json('next_page_url'));
    }

    /**
     * Readable cursor pagination decodes generated query URLs.
     */
    public function test_readable_cursor_pagination_outputs_readable_query_urls(): void
    {
        User::factory()->count(5)->create();
        $this->registerEloquentBuilderCursorRoute(decodeQueryUrls: true);

        $response = $this->getJson('/testing/json-api-eloquent-builder-cursor?page[size]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->assertStringContainsString('page[size]=1', (string) $response->json('next_page_url'));
        $this->assertStringContainsString('page[cursor]=', (string) $response->json('next_page_url'));
    }

    /**
     * Custom page names control request parameters and navigable pagination URLs.
     */
    #[DataProvider('paginationBuildersAndUrlFormats')]
    public function test_custom_page_name_is_used_for_number_pagination(bool $eloquent, bool $readable): void
    {
        $users = User::factory()->count(5)->create();
        Route::get('/testing/custom-pagination', static function () use ($eloquent, $readable): LengthAwarePaginator {
            $query = $eloquent ? User::query() : DB::table('users');

            return $query->orderBy('id')
                ->jsonApiPaginate(pageName: 'postPage')
                ->withReadableQueryUrls($readable);
        });

        $response = $this->getJson('/testing/custom-pagination?postPage[size]=1&postPage[number]=2&page[size]=2&page[number]=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('data.0.id', $users[1]->id);

        $nextUrl = (string) $response->json('next_page_url');
        parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $parameters);
        $this->assertSame(['postPage' => ['size' => '1', 'number' => '3']], $parameters);
        $this->assertStringContainsString($readable ? 'postPage[number]=3' : 'postPage%5Bnumber%5D=3', $nextUrl);
        $this->assertStringContainsString($readable ? 'postPage[size]=1' : 'postPage%5Bsize%5D=1', $nextUrl);

        $this->getJson($nextUrl)->assertOk()
            ->assertJsonPath('current_page', 3)
            ->assertJsonPath('data.0.id', $users[2]->id);
        $this->getJson((string) $response->json('prev_page_url'))->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('data.0.id', $users[0]->id);
    }

    /**
     * Custom cursor names control request parameters and navigable pagination URLs.
     */
    #[DataProvider('paginationBuildersAndUrlFormats')]
    public function test_custom_page_name_is_used_for_cursor_pagination(bool $eloquent, bool $readable): void
    {
        $users = User::factory()->count(5)->create();
        Route::get('/testing/custom-cursor-pagination', static function () use ($eloquent, $readable): CursorPaginator {
            $query = $eloquent ? User::query() : DB::table('users');

            return $query->orderBy('id')
                ->jsonApiCursorPaginate(pageName: 'postPage')
                ->withReadableQueryUrls($readable);
        });
        $parameters = http_build_query([
            'postPage' => ['size' => 1, 'cursor' => new Cursor(['id' => $users[0]->id])->encode()],
            'page' => ['size' => 2, 'cursor' => new Cursor(['id' => $users[2]->id])->encode()],
        ]);

        $response = $this->getJson('/testing/custom-cursor-pagination?' . $parameters);

        $response->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('data.0.id', $users[1]->id);

        $nextUrl = (string) $response->json('next_page_url');
        parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $nextParameters);
        $this->assertSame(['postPage' => ['size' => '1', 'cursor' => $response->json('next_cursor')]], $nextParameters);
        $this->assertStringContainsString($readable ? 'postPage[cursor]=' : 'postPage%5Bcursor%5D=', $nextUrl);
        $this->assertStringContainsString($readable ? 'postPage[size]=1' : 'postPage%5Bsize%5D=1', $nextUrl);

        $this->getJson($nextUrl)->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('data.0.id', $users[2]->id);
        $this->getJson((string) $response->json('prev_page_url'))->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('data.0.id', $users[0]->id);
    }

    /**
     * Multiple paginators in one request read only their own parameter groups.
     */
    public function test_pagination_parameter_groups_are_independent(): void
    {
        $users = User::factory()->count(8)->create();
        Route::get('/testing/multiple-paginators', static function (): array {
            $paginators = [];

            foreach (['postPage', 'commentPage', 'page'] as $pageName) {
                $paginators[$pageName] = [
                    'number' => User::query()->orderBy('id')->jsonApiPaginate(pageName: $pageName),
                    'cursor' => User::query()->orderBy('id')->jsonApiCursorPaginate(pageName: $pageName),
                ];
            }

            return $paginators;
        });
        $parameters = http_build_query([
            'postPage' => ['size' => 1, 'number' => 2, 'cursor' => new Cursor(['id' => $users[0]->id])->encode()],
            'commentPage' => ['size' => 2, 'number' => 3, 'cursor' => new Cursor(['id' => $users[3]->id])->encode()],
            'page' => ['size' => 3, 'number' => 2, 'cursor' => new Cursor(['id' => $users[2]->id])->encode()],
        ]);

        $response = $this->getJson('/testing/multiple-paginators?' . $parameters)->assertOk();

        foreach (['number', 'cursor'] as $pagination) {
            $response->assertJsonPath('postPage.' . $pagination . '.per_page', 1)
                ->assertJsonPath('postPage.' . $pagination . '.data.0.id', $users[1]->id)
                ->assertJsonPath('commentPage.' . $pagination . '.per_page', 2)
                ->assertJsonPath('commentPage.' . $pagination . '.data.0.id', $users[4]->id)
                ->assertJsonPath('page.' . $pagination . '.per_page', 3)
                ->assertJsonPath('page.' . $pagination . '.data.0.id', $users[3]->id);
        }
    }

    /**
     * Missing or invalid custom parameters do not fall back to another group.
     */
    #[DataProvider('invalidCustomPageParameters')]
    public function test_custom_page_name_keeps_existing_parameter_defaults(string $customParameters): void
    {
        $users = User::factory()->count(20)->create();
        Route::get('/testing/custom-pagination-defaults', static fn (): array => [
            'number' => User::query()->orderBy('id')->jsonApiPaginate(pageName: 'postPage'),
            'cursor' => User::query()->orderBy('id')->jsonApiCursorPaginate(pageName: 'postPage'),
        ]);
        $parameters = http_build_query([
            'page' => ['size' => 1, 'number' => 3, 'cursor' => new Cursor(['id' => $users[1]->id])->encode()],
        ]);

        $response = $this->getJson('/testing/custom-pagination-defaults?' . $parameters . '&' . $customParameters)
            ->assertOk()
            ->assertJsonPath('number.current_page', 1);

        foreach (['number', 'cursor'] as $pagination) {
            $response->assertJsonPath($pagination . '.per_page', 15)
                ->assertJsonPath($pagination . '.data.0.id', $users[0]->id);

            $nextUrl = (string) $response->json($pagination . '.next_page_url');
            parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $nextParameters);
            $this->assertSame(['postPage'], array_keys($nextParameters));
            $this->assertSame([$pagination], array_keys($nextParameters['postPage']));
        }
    }

    /**
     * Explicit pagination arguments keep precedence with the new parameter position.
     */
    #[DataProvider('paginationBuildersAndArgumentStyles')]
    public function test_explicit_number_pagination_arguments_take_precedence(bool $eloquent, bool $named): void
    {
        $users = User::factory()->count(6)->create();
        Route::get('/testing/explicit-pagination', static function () use ($eloquent, $named): LengthAwarePaginator {
            $query = ($eloquent ? User::query() : DB::table('users'))->orderBy('id');

            return $named
                ? $query->jsonApiPaginate(perPage: 2, columns: ['id'], pageName: 'postPage', page: 2, total: static fn (): int => 5)
                : $query->jsonApiPaginate(2, ['id'], 'postPage', 2, 5);
        });

        $this->getJson('/testing/explicit-pagination?postPage[size]=1&postPage[number]=3')
            ->assertOk()
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 5)
            ->assertJsonPath('data', [['id' => $users[2]->id], ['id' => $users[3]->id]]);
    }

    /**
     * Explicit cursor objects and strings take precedence with custom page names.
     */
    #[DataProvider('paginationBuildersAndArgumentStyles')]
    public function test_explicit_cursor_pagination_arguments_take_precedence(bool $eloquent, bool $named): void
    {
        $users = User::factory()->count(5)->create();
        $cursor = new Cursor(['id' => $users[1]->id]);
        Route::get('/testing/explicit-cursor-pagination', static function () use ($cursor, $eloquent, $named): CursorPaginator {
            $query = ($eloquent ? User::query() : DB::table('users'))->orderBy('id');

            return $named
                ? $query->jsonApiCursorPaginate(perPage: 2, columns: ['id'], pageName: 'postPage', cursor: $cursor)
                : $query->jsonApiCursorPaginate(2, ['id'], 'postPage', $cursor->encode());
        });
        $parameters = http_build_query([
            'postPage' => ['size' => 1, 'cursor' => new Cursor(['id' => $users[0]->id])->encode()],
        ]);

        $this->getJson('/testing/explicit-cursor-pagination?' . $parameters)
            ->assertOk()
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('data', [['id' => $users[2]->id], ['id' => $users[3]->id]]);
    }

    /**
     * @return \Iterator<string, array{bool, bool}>
     */
    public static function paginationBuildersAndUrlFormats(): Iterator
    {
        yield 'eloquent with encoded URLs' => [true, false];
        yield 'eloquent with readable URLs' => [true, true];
        yield 'query builder with encoded URLs' => [false, false];
        yield 'query builder with readable URLs' => [false, true];
    }

    /**
     * @return \Iterator<string, array{bool, bool}>
     */
    public static function paginationBuildersAndArgumentStyles(): Iterator
    {
        yield 'eloquent with positional arguments' => [true, false];
        yield 'eloquent with named arguments' => [true, true];
        yield 'query builder with positional arguments' => [false, false];
        yield 'query builder with named arguments' => [false, true];
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function invalidCustomPageParameters(): Iterator
    {
        yield 'missing group' => [''];
        yield 'scalar group' => ['postPage=invalid'];
        yield 'invalid values' => ['postPage[size]=-1&postPage[number]=invalid&postPage[cursor]=invalid'];
        yield 'zero values' => ['postPage[size]=0&postPage[number]=0'];
        yield 'nested values' => ['postPage[size][nested]=1&postPage[number][nested]=2&postPage[cursor][nested]=invalid'];
    }

    /**
     * Register the query builder test route.
     */
    protected function registerQueryBuilderRoute(): void
    {
        Route::get('/testing/json-api-query-builder', static function (): LengthAwarePaginator {
            return DB::table('users')
                ->orderBy('id')
                ->jsonApiPaginate(2);
        })->name('testing.query-builder.index');
    }

    /**
     * Register the standard paginator test route.
     */
    protected function registerStandardPaginationRoute(): void
    {
        Route::get('/testing/standard-pagination', static function (): LengthAwarePaginator {
            return User::query()
                ->orderBy('id')
                ->paginate(2)
                ->withQueryString()
                ->withReadableQueryUrls();
        })->name('testing.standard-pagination.index');
    }

    /**
     * Register the Eloquent builder test route.
     */
    protected function registerEloquentBuilderRoute(
        ?int $perPage = null,
        ?bool $decodeQueryUrls = null,
    ): void {
        Route::get('/testing/json-api-eloquent-builder', static function () use ($decodeQueryUrls, $perPage): LengthAwarePaginator {
            $paginator = User::query()
                ->orderBy('id')
                ->jsonApiPaginate(
                    perPage: $perPage,
                );

            if ($decodeQueryUrls === null) {
                return $paginator;
            }

            if ($decodeQueryUrls) {
                return $paginator->withReadableQueryUrls();
            }

            return $paginator->withReadableQueryUrls(false);
        })->name('testing.eloquent-builder.index');
    }

    /**
     * Register the query builder cursor test route.
     */
    protected function registerQueryBuilderCursorRoute(): void
    {
        Route::get('/testing/json-api-query-builder-cursor', static function (): CursorPaginator {
            return DB::table('users')
                ->orderBy('id')
                ->jsonApiCursorPaginate(2);
        })->name('testing.query-builder-cursor.index');
    }

    /**
     * Register the Eloquent builder cursor test route.
     */
    protected function registerEloquentBuilderCursorRoute(
        ?int $perPage = null,
        ?bool $decodeQueryUrls = null,
    ): void {
        Route::get('/testing/json-api-eloquent-builder-cursor', static function () use ($decodeQueryUrls, $perPage): CursorPaginator {
            $paginator = User::query()
                ->orderBy('id')
                ->jsonApiCursorPaginate(
                    perPage: $perPage,
                );

            if ($decodeQueryUrls === null) {
                return $paginator;
            }

            if ($decodeQueryUrls) {
                return $paginator->withReadableQueryUrls();
            }

            return $paginator->withReadableQueryUrls(false);
        })->name('testing.eloquent-builder-cursor.index');
    }
}
