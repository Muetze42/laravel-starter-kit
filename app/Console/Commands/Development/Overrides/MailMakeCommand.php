<?php

declare(strict_types=1);

namespace App\Console\Commands\Development\Overrides;

use App\Console\Commands\Concerns\ArgumentNameSuffixTrait;
use Illuminate\Foundation\Console\MailMakeCommand as Command;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:mail')]
class MailMakeCommand extends Command
{
    use ArgumentNameSuffixTrait;

    /**
     * Retrieves the suffix for the argument name.
     */
    public function getSuffix(): string
    {
        return 'Mail';
        // return $this->type;
    }

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    #[Override]
    public function handle(): void
    {
        $this->ensureNameHasSuffix();

        parent::handle();
    }
}
