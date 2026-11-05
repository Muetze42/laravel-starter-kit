<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Closure;

class ComposerInstaller
{
    /**
     * Inject the process boundary.
     */
    public function __construct(protected ProcessRunner $runner)
    {
        //
    }

    /**
     * Install only missing dependencies through the package manager.
     *
     * @param  array<string, array<string, string>>  $dependencies
     * @param  Closure(string): void  $output
     */
    public function install(string $root, array $dependencies, Closure $output): void
    {
        foreach ($dependencies as $category => $packages) {
            if ($packages === []) {
                continue;
            }

            $command = ['composer', 'require', '--no-interaction', '--no-scripts', '--minimal-changes'];
            if ($category === 'development') {
                $command[] = '--dev';
            }

            foreach ($packages as $name => $version) {
                $command[] = $name . ':' . $version;
            }

            $this->runner->run($command, $root, $output);
        }
    }
}
