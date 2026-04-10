<?php

namespace App\Console\Commands\Concerns;

trait ArgumentNameSuffixTrait
{
    /**
     * Retrieves the suffix for the argument name.
     */
    abstract public function getSuffix(): string;

    protected function ensureNameHasPrefix(): void
    {
        $name = $this->argument('name');

        if (! str_ends_with((string) $name, $this->getSuffix())) {
            $this->input->setArgument('name', $name . $this->getSuffix());
        }
    }
}
