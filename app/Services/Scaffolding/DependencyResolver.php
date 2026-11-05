<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use App\Services\Scaffolding\Contracts\PresetInterface;
use Composer\Semver\Semver;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use RuntimeException;

class DependencyResolver
{
    /**
     * Inject manifest validation.
     */
    public function __construct(protected ProjectFiles $files, protected DependencyLocks $locks)
    {
        //
    }

    /**
     * Resolve absent dependencies without replacing compatible declarations.
     *
     * @return array<string, array<string, array<string, string>>>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function resolve(string $root, PresetInterface $preset): array
    {
        $result = [];
        $dependencies = $preset->dependencies();
        $locked = $this->locks->versions($root);
        $managers = [
            'composer' => ['production' => 'require', 'development' => 'require-dev'],
            'pnpm' => ['production' => 'dependencies', 'development' => 'devDependencies'],
        ];
        foreach ($managers as $manager => $categories) {
            $manifest = $this->files->json($root . '/' . ($manager === 'composer' ? 'composer.json' : 'package.json'));
            foreach ($categories as $category => $key) {
                $existing = $this->files->strings($manifest, $key);
                $opposite = $this->files->strings($manifest, $categories[$category === 'production'
                    ? 'development' : 'production']);
                if ($manager === 'pnpm') {
                    $opposite = [...$opposite, ...$this->files->strings($manifest, 'peerDependencies'),
                        ...$this->files->strings($manifest, 'optionalDependencies')];
                }

                $result[$manager][$category] = [];
                $packages = $dependencies[$manager][$category] ?? [];
                foreach ($packages as $name => $version) {
                    if (isset($opposite[$name])) {
                        throw new RuntimeException($name . ' is in the wrong category; move it to '
                            . $key . ' before installing.');
                    }

                    if (isset($existing[$name])) {
                        if (! Semver::satisfies($version, $existing[$name])
                            || (isset($locked[$manager][$name])
                                && ! Semver::satisfies($locked[$manager][$name], '^' . $version))) {
                            throw new RuntimeException($name . ' conflicts with ' . $version
                                . '; reconcile ' . $key . ' before installing.');
                        }

                        continue;
                    }

                    $result[$manager][$category][$name] = $version;
                }
            }
        }

        return $result;
    }
}
