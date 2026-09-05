<?php

namespace App\Console\Commands\Development\Overrides;

use App\Console\Commands\Concerns\ArgumentNameSuffixTrait;
use Illuminate\Foundation\Console\ResourceMakeCommand as Command;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:resource')]
class ResourceMakeCommand extends Command
{
    use ArgumentNameSuffixTrait;

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    #[Override]
    public function handle(): void
    {
        $this->ensureNameHasSuffix();

        // @todo: Force use JSON:API for resources or remove.
        // $this->input->setOption('json-api', true);

        parent::handle();
    }

    /**
     * Replace the class name for the given stub.
     */
    protected function replaceClass($stub, $name): string
    {
        $class = parent::replaceClass($stub, $name);

        $modelName = Str::endsWith($name, 'Resource') ?
            Str::substr(class_basename($name), 0, -8) :
            class_basename($name);

        return str_replace(['{{model}}', '{{ model }}'], '\\' . $this->qualifyModel($modelName), $class);
    }

    /**
     * Retrieves the suffix for the argument name.
     */
    public function getSuffix(): string
    {
        return $this->type;
    }
}
