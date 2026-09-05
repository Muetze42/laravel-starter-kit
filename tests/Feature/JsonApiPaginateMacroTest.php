<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
     * Register the Eloquent builder test route.
     */
    protected function registerEloquentBuilderRoute(?int $perPage = null): void
    {
        Route::get('/testing/json-api-eloquent-builder', static function () use ($perPage): LengthAwarePaginator {
            return User::query()
                ->orderBy('id')
                ->jsonApiPaginate(
                    perPage: $perPage,
                );
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
    protected function registerEloquentBuilderCursorRoute(?int $perPage = null): void
    {
        Route::get('/testing/json-api-eloquent-builder-cursor', static function () use ($perPage): CursorPaginator {
            return User::query()
                ->orderBy('id')
                ->jsonApiCursorPaginate(
                    perPage: $perPage,
                );
        })->name('testing.eloquent-builder-cursor.index');
    }
}
