<?php

declare(strict_types=1);

namespace App\Services\Scaffolding\Presets;

class SveltePreset extends AbstractInertiaPreset
{
    /**
     * Identify the preset.
     */
    public function identifier(): string
    {
        return 'svelte';
    }

    /**
     * Supply the framework dependencies alongside shared Inertia tooling.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function dependencies(): array
    {
        $dependencies = parent::dependencies();
        $dependencies['pnpm'] = [
            'production' => [
                ...$dependencies['pnpm']['production'],
                '@sentry/svelte' => '11.11.0',
                'svelte' => '5.57.3',
                '@inertiajs/svelte' => '3.11.0',
            ],
            'development' => [
                ...$dependencies['pnpm']['development'],
                '@sveltejs/vite-plugin-svelte' => '7.3.1',
                'svelte-check' => '4.7.8',
                'eslint-plugin-svelte' => '3.24.0',
                'prettier-plugin-svelte' => '4.1.1',
            ],
        ];

        return $dependencies;
    }

    /**
     * Describe the application entry suffix.
     */
    public function entryExtension(): string
    {
        return 'ts';
    }

    /**
     * Describe the page component suffix.
     */
    public function pageExtension(): string
    {
        return 'svelte';
    }

    /**
     * Supply the framework plugin import.
     */
    public function pluginImport(): string
    {
        return "import { svelte as frontendFramework } from '@sveltejs/vite-plugin-svelte'";
    }

    /**
     * Supply the framework plugin expression.
     */
    public function pluginExpression(): string
    {
        return 'frontendFramework()';
    }

    /**
     * Supply the type checking command.
     */
    public function typecheck(): string
    {
        return 'svelte-check --tsconfig ./tsconfig.json && tsc --noEmit';
    }
}
