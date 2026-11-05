<?php

declare(strict_types=1);

namespace App\Console\Commands\Development;

use App\Services\Scaffolding\Contracts\PresetInterface;
use App\Services\Scaffolding\FrontendInstaller;
use DirectoryIterator;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Override;
use ReflectionClass;
use Throwable;

use function Laravel\Prompts\select;

#[Signature('frontend:install {--skip-verification : Skip build, lint, formatting, and type checks}')]
class FrontendInstallCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Install a modular Inertia frontend preset while preserving the starter kit';

    /**
     * Select a preset and report installation progress.
     *
     * @throws Throwable
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function handle(FrontendInstaller $installer): void
    {
        $presets = $this->presets();
        $choices = array_keys($presets);
        if ($choices === []) {
            $this->fail('No frontend presets are configured.');
        }

        $selection = select(
            label: 'Which frontend preset would you like to install?',
            options: $choices,
        );
        $preset = is_string($selection) ? ($presets[$selection] ?? null) : null;
        if ($preset === null) {
            $this->fail('The selected frontend preset is unavailable.');
        }

        $installer->install(base_path(), $preset, (bool) $this->option('skip-verification'),
            function (string $buffer): void {
                $this->output->write($buffer);
            });

    }

    /** List the available scaffolding presets by their public identifiers.
     *
     * @return array<string, PresetInterface>
     *
     * @throws \ReflectionException
     */
    protected function presets(): array
    {
        $presets = [];
        foreach (new DirectoryIterator(app_path('Services/Scaffolding/Presets')) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $class = 'App\\Services\\Scaffolding\\Presets\\' . $file->getBasename('.php');
            if (! is_subclass_of($class, PresetInterface::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isInstantiable()) {
                continue;
            }

            $preset = new $class();
            $presets[$preset->identifier()] = $preset;
        }

        ksort($presets);

        return $presets;
    }
}
