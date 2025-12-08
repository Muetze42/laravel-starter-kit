<?php

namespace App\Console\Commands\Development;

use App\Console\Commands\Concerns\ArgumentNameSuffixTrait;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Console\ConsoleMakeCommand as Command;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:command')]
class ConsoleMakeCommand extends Command
{
    use ArgumentNameSuffixTrait;

    /**
     * Retrieves the suffix for the argument name.
     */
    public function getSuffix(): string
    {
        return 'Command';
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

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    #[Override]
    protected function replaceClass($stub, $name): string
    {
        $stub = GeneratorCommand::replaceClass($stub, $name);

        $command = $this->option('command');

        if (empty($command)) {
            $command = $this->getSignaturePrefix();

            if (str_ends_with($name, 'Command') && strlen($name) > 7) {
                $name = substr($name, 0, -7);
            }

            $defaultNamespace = str_replace('\\\\', '\\', $this->getDefaultNamespace($this->rootNamespace()));
            $commandClass = trim(str_replace($defaultNamespace, '', $name), '\\');

            $levels = explode('\\', $commandClass);
            $levels = array_map(Str::kebab(...), $levels);
            $command .= ':' . implode(':', $levels);
        }

        return str_replace(['dummy:command', '{{ command }}'], $command, $stub);
    }

    /**
     * Get the prefix for the name and signature of the console command.
     */
    protected function getSignaturePrefix(): string
    {
        return Str::of($this->rootNamespace())->classBasename()->lower()->value();
    }
}
