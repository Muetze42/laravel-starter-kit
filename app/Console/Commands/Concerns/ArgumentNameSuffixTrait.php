<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

trait ArgumentNameSuffixTrait
{
    /**
     * Retrieves the suffix for the argument name.
     */
    abstract public function getSuffix(): string;

    /**
     * Ensures that the provided name argument includes the required suffix.
     */
    protected function ensureNameHasPrefix(): void
    {
        $name = $this->argument('name');

        if (! str_ends_with((string) $name, $this->getSuffix())) {
            $this->input->setArgument('name', $name . $this->getSuffix());
        }
    }
}
