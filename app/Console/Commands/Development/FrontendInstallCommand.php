<?php

declare(strict_types=1);

namespace App\Console\Commands\Development;

use App\Services\Scaffolding\Contracts\PresetInterface;
use App\Services\Scaffolding\FrontendInstaller;
use DirectoryIterator;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use Override;
use ReflectionClass;
use Throwable;

use function Laravel\Prompts\confirm;
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
    public function handle(FrontendInstaller $installer, Filesystem $files): void
    {
        $presets = $this->presets();
        $choices = array_keys($presets);
        if ($choices === []) {
            $this->fail('No frontend presets are configured.');
        }

        $choices[] = 'Remove frontend installer';

        $selection = select(
            label: 'Which frontend preset would you like to install?',
            options: $choices,
        );
        if ($selection === 'Remove frontend installer') {
            $this->removeInstaller($files);

            return;
        }

        $preset = is_string($selection) ? ($presets[$selection] ?? null) : null;
        if ($preset === null) {
            $this->fail('The selected frontend preset is unavailable.');
        }

        $installer->install(base_path(), $preset, (bool) $this->option('skip-verification'),
            function (string $buffer): void {
                $this->output->write($buffer);
            });

        $this->removeInstaller($files, default: true);
    }

    /**
     * Remove the installer and its supporting files after explicit confirmation.
     *
     * @throws Throwable
     */
    protected function removeInstaller(Filesystem $files, bool $default = false): void
    {
        $paths = [
            'app/Services/Scaffolding',
            'stubs/frontend',
            'app/Console/Commands/Development/FrontendInstallCommand.php',
        ];

        if (! confirm('Permanently remove the frontend installer and all its supporting files?', default: $default)) {
            $this->info('Removal cancelled.');

            return;
        }

        foreach ($paths as $path) {
            $absolutePath = base_path($path);
            if (! $files->exists($absolutePath)) {
                continue;
            }

            $deleted = $files->isDirectory($absolutePath)
                ? $files->deleteDirectory($absolutePath)
                : $files->delete($absolutePath);

            if (! $deleted) {
                $this->fail('Could not remove: ' . $path);
            }
        }

        $this->info('Frontend installer removed.');
    }

    /**
     * List the available scaffolding presets by their public identifiers.
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
