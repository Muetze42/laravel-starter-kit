<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Scaffolding;

use App\Services\Scaffolding\FilePlanner;
use App\Services\Scaffolding\Presets\ReactPreset;
use App\Services\Scaffolding\Presets\SveltePreset;
use App\Services\Scaffolding\Presets\VuePreset;
use App\Services\Scaffolding\ProcessRunner;
use App\Services\Scaffolding\ProjectFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class FilePlannerTest extends TestCase
{
    /** The isolated project used by the generator. */
    protected string $root;

    /** Create a starter fixture without changing the working project. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/frontend-test-' . Str::uuid();
        $files = new Filesystem();
        foreach (['resources/js', 'resources/views', 'bootstrap', 'routes'] as $directory) {
            $files->ensureDirectoryExists($this->root . '/' . $directory);
        }
        foreach (['package.json', '.prettierrc', 'bootstrap/app.php', 'routes/web.php'] as $path) {
            $files->copy(base_path($path), $this->root . '/' . $path);
        }
        $files->copy(base_path('tests/Fixtures/Frontend/vite.config.js'), $this->root . '/vite.config.js');
        $files->put($this->root . '/resources/js/app.js', '');
        $files->link(base_path('node_modules'), $this->root . '/node_modules');
    }

    /** Remove only the fixture and its dependency symlink. */
    protected function tearDown(): void
    {
        $files = new Filesystem();
        $files->delete($this->root . '/node_modules');
        $files->deleteDirectory($this->root);
        parent::tearDown();
    }

    /** Execute generated configuration to verify plugins and preserved settings. */
    #[TestWith(['vue'])]
    #[TestWith(['react'])]
    #[TestWith(['svelte'])]
    public function test_generated_config_activates_plugins_and_preserves_existing_settings(string $framework): void
    {
        $preset = match ($framework) {
            'vue' => new VuePreset(),
            'react' => new ReactPreset(),
            'svelte' => new SveltePreset(),
        };
        $changes = $this->app->make(FilePlanner::class)->plan($this->root, $preset);
        $this->app->make(ProjectFiles::class)->writeChanges($this->root, $changes);

        $output = $this->app->make(ProcessRunner::class)->run([
            'node', base_path('tests/Fixtures/Frontend/evaluate-config.mjs'),
            $this->root . '/vite.config.ts', $framework,
        ], $this->root);

        $this->assertSame('', $output);
        $this->assertFileDoesNotExist($this->root . '/vite.config.js');
    }

    /** Typecheck generated declarations against the installed preset packages. */
    #[TestWith(['vue'])]
    #[TestWith(['react'])]
    #[TestWith(['svelte'])]
    public function test_generated_types_support_the_selected_framework(string $framework): void
    {
        $installed = getenv('FRONTEND_TEST_ROOT');
        if ($installed === false) {
            $this->markTestSkipped('Set FRONTEND_TEST_ROOT to isolated installed vue, react, and svelte projects.');
        }

        $files = new Filesystem();
        $files->delete($this->root . '/node_modules');
        $files->link($installed . '/' . $framework . '/node_modules', $this->root . '/node_modules');

        $preset = match ($framework) {
            'vue' => new VuePreset(),
            'react' => new ReactPreset(),
            'svelte' => new SveltePreset(),
        };
        $changes = $this->app->make(FilePlanner::class)->plan($this->root, $preset);
        $this->app->make(ProjectFiles::class)->writeChanges($this->root, $changes);
        $files->copy(base_path('tests/Fixtures/Frontend/shared-types.ts'), $this->root . '/resources/js/shared-types.ts');
        if ($framework === 'vue') {
            $files->copy(base_path('tests/Fixtures/Frontend/vue-types.ts'), $this->root . '/resources/js/vue-types.ts');
        }

        // Exclude the entry point so declarations cannot depend on its incidental imports.
        $project = $this->app->make(ProjectFiles::class);
        $typescript = $project->json($this->root . '/tsconfig.json');
        $typescript['include'] = ['resources/js/types/global.d.ts', 'resources/js/*-types.ts'];
        $files->put($this->root . '/tsconfig.types.json', $project->encode($typescript));
        $output = $this->app->make(ProcessRunner::class)->run([
            'node', $this->root . '/node_modules/typescript/bin/tsc', '--project', 'tsconfig.types.json',
        ], $this->root);

        $this->assertSame('', $output);
    }
}
