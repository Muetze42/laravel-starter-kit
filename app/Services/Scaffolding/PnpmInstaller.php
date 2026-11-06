<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Closure;
use Illuminate\Filesystem\Filesystem;

class PnpmInstaller
{
    /**
     * Inject the process boundary.
     */
    public function __construct(
        protected ProcessRunner $runner,
        protected Filesystem $files,
    ) {
        //
    }

    /**
     * Install the declared tooling before parsing the Vite configuration.
     *
     * @param  Closure(string): void  $output
     */
    public function installExistingDependencies(string $root, Closure $output): void
    {
        $this->runner->run([
            'pnpm', 'install', '--ignore-scripts', '--no-prod',
            $this->files->exists($root . '/pnpm-lock.yaml') ? '--frozen-lockfile' : '--no-frozen-lockfile',
        ], $root, $output);
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

            $command = ['pnpm', 'add', '--save-exact', '--ignore-scripts'];
            if ($category === 'development') {
                $command[] = '--save-dev';
            }

            if ($category === 'production') {
                $command[] = '--save-prod';
            }

            foreach ($packages as $name => $version) {
                $command[] = $name . '@' . $version;
            }

            $this->runner->run($command, $root, $output);
        }
    }
}
