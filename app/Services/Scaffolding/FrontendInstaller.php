<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use App\Services\Scaffolding\Contracts\PresetInterface;
use Closure;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;

class FrontendInstaller
{
    /**
     * Inject the installation phases.
     */
    public function __construct(
        protected DependencyResolver $dependencies,
        protected DependencyLocks $locks,
        protected FilePlanner $planner,
        protected ComposerInstaller $composer,
        protected PnpmInstaller $pnpm,
        protected ProcessRunner $runner,
        protected ProjectFiles $project,
    ) {
        //
    }

    /**
     * Generate the selected frontend and install its dependencies.
     *
     * @param  Closure(string): void  $output
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function install(string $root, PresetInterface $preset, bool $skipVerification, Closure $output): void
    {
        $originalLocks = $this->locks->versions($root);
        $dependencies = $this->dependencies->resolve($root, $preset);
        $changes = $this->planner->plan($root, $preset);
        $this->project->writeChanges($root, $changes);

        $this->composer->install($root, $dependencies['composer'], $output);
        $this->pnpm->install($root, $dependencies['pnpm'], $output);
        $this->locks->assertPreserved($root, $originalLocks);
        $this->runner->run([PHP_BINARY, 'artisan', 'package:discover', '--no-interaction'], $root, $output);
        $this->runner->run([PHP_BINARY, 'artisan', 'inertia:middleware', '--no-interaction'], $root, $output);

        $formatPaths = array_keys(array_filter($changes, fn (?string $contents,
            string $path): bool => $contents !== null && preg_match('/\\.(ts|tsx|vue|svelte|json)$/',
                $path) === 1, ARRAY_FILTER_USE_BOTH));
        $this->runner->run(['pnpm', 'exec', 'prettier', '--write', ...$formatPaths], $root, $output);
        if (! $skipVerification) {
            foreach (['build', 'lint', 'format:check', 'typecheck'] as $script) {
                $this->runner->run(['pnpm', 'run', $script], $root, $output);
            }
        }

        $output($skipVerification
            ? "Frontend installed. Verification SKIPPED (--skip-verification).\n"
            : "Frontend installed. Build, lint, formatting, and type checks passed.\n");
        $output("Development SSR: pnpm run dev\nProduction SSR: php artisan inertia:start-ssr\n");
    }
}
