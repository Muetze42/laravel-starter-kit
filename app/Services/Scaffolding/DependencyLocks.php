<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class DependencyLocks
{
    /**
     * Inject manifest readers.
     */
    public function __construct(protected ProjectFiles $project, protected Filesystem $files)
    {
        //
    }

    /** Read resolved direct dependencies without querying a registry.
     *
     * @return array<string, array<string, string>>
     *
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function versions(string $root): array
    {
        return ['composer' => $this->composer($root), 'pnpm' => $this->pnpm($root)];
    }

    /**
     * Read locked Composer dependencies.
     *
     * @return array<string, string>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function composer(string $root): array
    {
        $composer = $this->project->json($root . '/composer.lock');
        $php = [];
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $composer[$section] ?? [];
            if (! is_array($packages)) {
                throw new RuntimeException('Invalid composer.lock ' . $section);
            }

            foreach ($packages as $package) {
                if (! is_array($package) || ! is_string($package['name'] ?? null)
                    || ! is_string($package['version'] ?? null)) {
                    throw new RuntimeException('Invalid composer.lock package.');
                }

                $php[$package['name']] = $package['version'];
            }
        }

        $manifest = $this->project->json($root . '/composer.json');
        $names = array_keys([...$this->project->strings($manifest, 'require'),
            ...$this->project->strings($manifest, 'require-dev')]);

        return collect($php)->only($names)->all();
    }

    /** Read the application document in pnpm's lockfile.
     * @return array<string, string>
     *
     * @throws FileNotFoundException
     */
    protected function pnpm(string $root): array
    {
        if (! $this->files->exists($root . '/pnpm-lock.yaml')) {
            return [];
        }

        $javascript = [];
        $found = false;
        // pnpm 12 writes separate package-manager and application YAML documents.
        $documents = preg_split('/^---\s*$/m', $this->files->get($root . '/pnpm-lock.yaml'));
        foreach ($documents ?: [] as $document) {
            $parsed = Yaml::parse($document);
            if (! is_array($parsed) || ! isset($parsed['importers'])) {
                continue;
            }

            $importers = $parsed['importers'];
            if (! is_array($importers) || array_keys($importers) !== ['.'] || ! is_array($importers['.'])) {
                throw new RuntimeException(
                    'Only a single-project pnpm lockfile is supported; separate workspace ' . 'installation manually.');
            }

            $found = true;
            foreach (['dependencies', 'devDependencies', 'optionalDependencies'] as $section) {
                $packages = $importers['.'][$section] ?? [];
                if (! is_array($packages)) {
                    throw new RuntimeException('Invalid pnpm-lock.yaml ' . $section);
                }

                foreach ($packages as $name => $package) {
                    if (! is_string($name) || ! is_array($package) || ! is_string($package['version'] ?? null)) {
                        throw new RuntimeException('Invalid pnpm-lock.yaml dependency.');
                    }

                    $javascript[$name] = Str::before($package['version'], '(');
                }
            }
        }

        if (! $found) {
            throw new RuntimeException(
                'pnpm-lock.yaml is missing the root importer; run pnpm install before ' . 'installing a frontend.');
        }

        return $javascript;
    }

    /** Reject package-manager updates to existing direct dependencies.
     *
     * @param  array<string, array<string, string>>  $original
     *
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function assertPreserved(string $root, array $original): void
    {
        $current = $this->versions($root);
        foreach ($original as $manager => $packages) {
            foreach ($packages as $name => $version) {
                if (($current[$manager][$name] ?? null) !== $version) {
                    throw new RuntimeException($name . ' changed from its locked version ' . $version
                        . '; reconcile dependency constraints manually.');
                }
            }
        }
    }
}
