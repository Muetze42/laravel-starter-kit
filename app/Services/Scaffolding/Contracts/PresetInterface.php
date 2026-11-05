<?php

declare(strict_types=1);

namespace App\Services\Scaffolding\Contracts;

interface PresetInterface
{
    /**
     * Identify the preset.
     */
    public function identifier(): string;

    /**
     * Supply Composer and pnpm dependencies by production or development category.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function dependencies(): array;

    /**
     * Describe the application entry suffix.
     */
    public function entryExtension(): string;

    /**
     * Describe the page component suffix.
     */
    public function pageExtension(): string;

    /**
     * Supply the framework plugin import.
     */
    public function pluginImport(): string;

    /**
     * Supply the framework plugin expression.
     */
    public function pluginExpression(): string;

    /**
     * Supply the type checking command.
     */
    public function typecheck(): string;
}
