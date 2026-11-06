<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use App\Services\Scaffolding\Contracts\PresetInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use PhpParser\ParserFactory;
use RuntimeException;

class FilePlanner
{
    /**
     * Inject source editing and manifest helpers.
     */
    public function __construct(
        protected ConfigurationEditor $editor,
        protected Filesystem $files,
        protected ProjectFiles $project,
    ) {
        //
    }

    /**
     * Build all source changes before any dependency mutation.
     *
     * @return array<string, string|null>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function plan(string $root, PresetInterface $preset): array
    {
        foreach (['vite.config.ts', 'vite.config.mjs', 'tsconfig.json', 'eslint.config.ts',
            'eslint.config.js', 'eslint.config.mjs', '.eslintrc', '.eslintrc.json', '.prettierrc.js',
            'prettier.config.js', 'svelte.config.js', 'resources/views/app.blade.php',
            'app/Http/Middleware/HandleInertiaRequests.php', 'resources/js/sentry.ts',
            'resources/js/types/global.d.ts'] as $destination) {
            if ($this->files->exists($root . '/' . $destination)) {
                throw new RuntimeException($destination . ' already exists; integrate it manually before installing.');
            }
        }

        $changes = $this->editor->javascript($root, $preset);
        $entry = 'resources/js/app.' . $preset->entryExtension();
        $changes[$entry] = ($changes[$entry] ?? '') . "\n" . $this->template($preset->identifier() . '/app.stub');
        $page = 'resources/js/pages/Welcome.' . $preset->pageExtension();
        if ($this->files->exists($root . '/' . $page)) {
            throw new RuntimeException($page . ' already exists.');
        }

        $changes['resources/js/sentry.ts'] = $this->template('shared/sentry.ts.stub');
        $changes['resources/js/types/global.d.ts'] = $this->template('shared/global.d.ts.stub');
        $changes[$page] = $this->template($preset->identifier() . '/Welcome.stub');
        $changes = [...$changes, ...$this->server($root, $preset, $entry, $changes)];
        $changes['package.json'] = $this->package($root, $preset, $entry, $changes);
        $changes['.prettierrc'] = $this->prettier($root, $preset);
        $changes['tsconfig.json'] = $this->typescript($preset);
        if ($preset->identifier() === 'svelte') {
            $changes['svelte.config.js'] = $this->template('svelte/svelte.config.js.stub');
        }

        $changes['eslint.config.ts'] = $this->template($preset->identifier() . '/eslint.config.ts.stub');
        foreach ($changes as $path => $contents) {
            $this->project->writable($root, $path);
            if ($contents !== null && Str::endsWith($path, '.php') && ! Str::endsWith($path, '.blade.php')) {
                new ParserFactory()->createForNewestSupportedVersion()->parse($contents);
            }
        }

        return $changes;
    }

    /** Plan server integration and replace the starter's root route.
     * @param  array<string, string|null>  $changes
     * @return array<string, string|null>
     *
     * @throws FileNotFoundException
     */
    protected function server(string $root, PresetInterface $preset, string $entry, array $changes): array
    {
        $changes['resources/views/app.blade.php'] = Str::replace(["        {{ refresh }}\n", '{{ entry }}'],
            [$preset->identifier() === 'react' ? "        @viteReactRefresh\n" : '', $entry],
            $this->template('shared/app.blade.php.stub'));
        $bootstrap = $this->files->get($root . '/bootstrap/app.php');
        if (Str::contains($bootstrap, ['HandleInertiaRequests', 'Inertia\\'])) {
            throw new RuntimeException('bootstrap/app.php already references Inertia; integrate it manually.');
        }

        $changes['bootstrap/app.php'] = $this->editor->middleware($bootstrap);
        $changes['routes/web.php'] = $this->editor->routes($this->files->get($root . '/routes/web.php'));
        foreach ($this->files->allFiles($root . '/resources/views') as $view) {
            $contents = $view->getContents();
            $updated = $contents;
            foreach ($changes as $oldPath => $replacement) {
                if ($replacement === null && Str::endsWith($oldPath, '.js')) {
                    $newPath = $oldPath === 'resources/js/app.js' ? $entry : Str::replaceEnd('.js', '.ts', $oldPath);
                    $updated = $this->reference($oldPath, $newPath, $updated);
                }
            }

            if ($updated !== $contents) {
                $changes['resources/views/' . $view->getRelativePathname()] = $updated;
            }
        }

        return $changes;
    }

    /** Merge scoped frontend scripts with the existing package manifest.
     * @param  array<string, string|null>  $changes
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function package(string $root, PresetInterface $preset, string $entry, array $changes): string
    {
        $package = $this->project->json($root . '/package.json');
        $scripts = $this->project->strings($package, 'scripts');
        foreach ($scripts as $name => $script) {
            foreach ($changes as $oldPath => $replacement) {
                if ($replacement === null && Str::endsWith($oldPath, '.js')) {
                    $newPath = $oldPath === 'resources/js/app.js' ? $entry : Str::replaceEnd('.js', '.ts', $oldPath);
                    $scripts[$name] = $this->reference($oldPath, $newPath, $scripts[$name]);
                }
            }
        }

        $scope = '"resources/js/**/*.{ts,tsx,vue,svelte}" vite.config.ts eslint.config.ts tsconfig.json';
        $lintScope = 'resources/js vite.config.ts eslint.config.ts';
        if ($preset->identifier() === 'svelte') {
            $scope .= ' svelte.config.js';
            $lintScope .= ' svelte.config.js';
        }

        if (($scripts['build'] ?? null) === 'vite build') {
            unset($scripts['build']);
        }

        $requested = [
            'build' => 'vite build && vite build --ssr',
            'dev' => 'vite',
            'lint' => 'eslint --flag unstable_native_nodejs_ts_config ' . $lintScope,
            'lint:fix' => 'eslint --flag unstable_native_nodejs_ts_config ' . $lintScope . ' --fix',
            'format' => 'prettier --write ' . $scope,
            'format:check' => 'prettier --check ' . $scope,
            'typecheck' => $preset->typecheck(),
        ];
        foreach ($requested as $name => $script) {
            if (isset($scripts[$name]) && $scripts[$name] !== $script) {
                throw new RuntimeException('package.json script ' . $name . ' conflicts; rename it before installing.');
            }

            $scripts[$name] = $script;
        }

        $package['scripts'] = $scripts;

        return $this->project->encode($package);
    }

    /**
     * Preserve formatting preferences while ordering framework plugins.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function prettier(string $root, PresetInterface $preset): string
    {
        $prettier = $this->project->json($root . '/.prettierrc');
        $plugins = $prettier['plugins'] ?? [];
        if (! is_array($plugins) || ! array_is_list($plugins)) {
            throw new RuntimeException('.prettierrc plugins must be a list of package names.');
        }

        $validatedPlugins = [];
        foreach ($plugins as $plugin) {
            if (! is_string($plugin)) {
                throw new RuntimeException('.prettierrc plugins must be strings.');
            }

            if (! in_array($plugin, ['prettier-plugin-tailwindcss', 'prettier-plugin-svelte'], true)) {
                $validatedPlugins[] = $plugin;
            }
        }

        $plugins = $validatedPlugins;
        if ($preset->identifier() === 'svelte') {
            $plugins[] = 'prettier-plugin-svelte';
        }

        $plugins[] = 'prettier-plugin-tailwindcss';

        return $this->project->encode(array_replace($prettier, ['semi' => false, 'singleQuote' => true,
            'trailingComma' => 'none', 'bracketSpacing' => true, 'plugins' => $plugins]));
    }

    /**
     * Configure framework types on the shared strict TypeScript template.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function typescript(PresetInterface $preset): string
    {
        $typescript = $this->project->json(base_path('stubs/frontend/shared/tsconfig.json.stub'));
        $typescript['include'] = array_values(array_unique([
            'resources/js/**/*.ts',
            'resources/js/**/*.' . $preset->entryExtension(),
            'resources/js/**/*.' . $preset->pageExtension(),
            'vite.config.ts',
            'eslint.config.ts',
        ]));
        $compiler = $typescript['compilerOptions'] ?? null;
        if (! is_array($compiler) || ! is_array($compiler['types'] ?? null)) {
            throw new RuntimeException('Invalid TypeScript template.');
        }

        if ($preset->identifier() === 'react') {
            $compiler['jsx'] = 'react-jsx';
            $compiler['types'][] = 'react';
            $compiler['types'][] = 'react-dom';
        }

        if ($preset->identifier() === 'svelte') {
            $compiler['types'][] = 'svelte';
        }

        $typescript['compilerOptions'] = $compiler;

        return $this->project->encode($typescript);
    }

    /**
     * Replace complete asset references without changing similarly named files.
     */
    protected function reference(string $oldPath, string $newPath, string $contents): string
    {
        return preg_replace_callback('/(?<![a-zA-Z0-9_.-])' . preg_quote($oldPath, '/')
            . '(?![a-zA-Z0-9_.-])/', fn (): string => $newPath, $contents)
            ?? throw new RuntimeException('Unable to migrate asset references.');
    }

    /**
     * Load a bundled source template.
     *
     * @throws FileNotFoundException
     */
    protected function template(string $path): string
    {
        return $this->files->get(base_path('stubs/frontend/' . $path));
    }
}
