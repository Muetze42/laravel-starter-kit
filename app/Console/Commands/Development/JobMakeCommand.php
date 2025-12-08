<?php

namespace App\Console\Commands\Development;

use App\Console\Commands\Concerns\ArgumentNameSuffixTrait;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Console\JobMakeCommand as Command;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:job')]
class JobMakeCommand extends Command
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
     * @throws FileNotFoundException
     */
    #[Override]
    public function handle(): ?bool
    {
        $this->ensureNameHasPrefix();

        return parent::handle();
    }
}
