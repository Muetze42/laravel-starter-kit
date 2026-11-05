<?php

declare(strict_types=1);

namespace App\Services\Scaffolding\Presets;

use App\Services\Scaffolding\Contracts\PresetInterface;

abstract class AbstractInertiaPreset implements PresetInterface
{
    /** Supply the dependencies shared by the bundled Inertia presets.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function dependencies(): array
    {
        return [
            'composer' => [
                'production' => [
                    'inertiajs/inertia-laravel' => '3.7.0',
                ],
                'development' => [],
            ],
            'pnpm' => [
                'production' => ['@sentry/node' => '11.11.0'],
                'development' => [
                    'typescript' => '6.0.3',
                    '@types/node' => '24.19.2',
                    'eslint' => '9.39.5',
                    '@eslint/js' => '9.39.5',
                    'typescript-eslint' => '8.72.1',
                    'globals' => '17.14.0',
                    'prettier' => '3.9.10',
                    'eslint-config-prettier' => '10.1.8',
                    'prettier-plugin-tailwindcss' => '0.8.1',
                    '@inertiajs/vite' => '3.11.0',
                    '@sentry/vite-plugin' => '11.11.0',
                ],
            ],
        ];
    }
}
