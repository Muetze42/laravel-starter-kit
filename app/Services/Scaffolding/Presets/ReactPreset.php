<?php

declare(strict_types=1);

namespace App\Services\Scaffolding\Presets;

class ReactPreset extends AbstractInertiaPreset
{
    /**
     * Identify the preset.
     */
    public function identifier(): string
    {
        return 'react';
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
                '@sentry/react' => '11.11.0',
                'react' => '19.3.1',
                'react-dom' => '19.3.1',
                '@inertiajs/react' => '3.11.0',
            ],
            'development' => [
                ...$dependencies['pnpm']['development'],
                '@types/react' => '19.3.0',
                '@types/react-dom' => '19.3.0',
                '@vitejs/plugin-react' => '6.2.1',
                'eslint-plugin-react' => '7.37.5',
                'eslint-plugin-react-hooks' => '7.1.1',
            ],
        ];

        return $dependencies;
    }

    /**
     * Describe the application entry suffix.
     */
    public function entryExtension(): string
    {
        return 'tsx';
    }

    /**
     * Describe the page component suffix.
     */
    public function pageExtension(): string
    {
        return 'tsx';
    }

    /**
     * Supply the framework plugin import.
     */
    public function pluginImport(): string
    {
        return "import frontendFramework from '@vitejs/plugin-react'";
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
        return 'tsc --noEmit';
    }
}
