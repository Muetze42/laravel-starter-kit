<?php

namespace App\Console\Commands\Development\Overrides;

use App\Console\Commands\Concerns\ArgumentNameSuffixTrait;
use Illuminate\Foundation\Console\InterfaceMakeCommand as Command;
use Override;

class InterfaceMakeCommand extends Command
{
    use ArgumentNameSuffixTrait;

    /**
     * Retrieves the suffix for the argument name.
     */
    public function getSuffix(): string
    {
        return $this->type;
    }

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    #[Override]
    public function handle(): ?bool
    {
        $this->ensureNameHasSuffix();

        return parent::handle();
    }
}
