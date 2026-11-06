<?php

declare(strict_types=1);

namespace App\Services\Scaffolding\Presets;

class VuePreset extends AbstractInertiaPreset
{
    /**
     * Identify the preset.
     */
    public function identifier(): string
    {
        return 'vue';
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
                '@sentry/vue' => '11.11.0',
                'vue' => '3.5.45',
                '@inertiajs/vue3' => '3.11.0',
            ],
            'development' => [
                ...$dependencies['pnpm']['development'],
                '@vitejs/plugin-vue' => '6.0.10',
                'vue-tsc' => '3.3.14',
                'eslint-plugin-vue' => '10.12.0',
                'vue-eslint-parser' => '10.4.1',
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
        return 'vue';
    }

    /**
     * Supply the framework plugin import.
     */
    public function pluginImport(): string
    {
        return "import frontendFramework from '@vitejs/plugin-vue'";
    }

    /**
     * Supply the framework plugin expression.
     */
    public function pluginExpression(): string
    {
        return 'frontendFramework({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } })';
    }

    /**
     * Supply the type checking command.
     */
    public function typecheck(): string
    {
        return 'vue-tsc --noEmit';
    }
}
