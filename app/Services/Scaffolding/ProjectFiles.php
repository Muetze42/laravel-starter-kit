<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ProjectFiles
{
    /**
     * Inject filesystem operations.
     */
    public function __construct(protected Filesystem $files)
    {
        //
    }

    /**
     * Decode a JSON object with actionable errors.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function json(string $path): array
    {
        $decoded = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw new RuntimeException($path . ' must contain a JSON object.');
        }

        $object = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException($path . ' must contain named JSON properties.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    /**
     * Encode project JSON consistently.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    public function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Validate a map of dependency declarations.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    public function strings(array $manifest, string $key): array
    {
        $values = $manifest[$key] ?? [];
        if (! is_array($values)) {
            throw new RuntimeException($key . ' must be an object.');
        }

        foreach ($values as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                throw new RuntimeException($key . ' must contain named string values.');
            }
        }

        return $values;
    }

    /**
     * Reject symlinks and unwritable destinations before mutation.
     */
    public function writable(string $root, string $relative): void
    {
        if (Str::startsWith($relative, '/') || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('Unsafe installation path: ' . $relative);
        }

        $path = $root;
        foreach (explode('/', $relative) as $part) {
            $path .= '/' . $part;
            if (is_link($path)) {
                throw new RuntimeException('Symlink destinations are unsupported: ' . $relative);
            }

            if (file_exists($path) && ! is_writable($path)) {
                throw new RuntimeException('Path is not writable: ' . $relative);
            }
        }
    }

    /**
     * Write replacements before removing obsolete files.
     *
     * @param  array<string, string|null>  $changes
     */
    public function writeChanges(string $root, array $changes): void
    {
        foreach ($changes as $path => $contents) {
            if ($contents === null) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($root . '/' . $path));
            $mode = $this->files->exists($root . '/' . $path) ? (fileperms($root . '/' . $path) & 0777) : 0644;
            $this->files->replace($root . '/' . $path, $contents, $mode);
        }

        foreach ($changes as $path => $contents) {
            if ($contents === null && $this->files->exists($root . '/' . $path)
                && ! $this->files->delete($root . '/' . $path)) {
                throw new RuntimeException('Could not remove obsolete file ' . $path);
            }
        }
    }
}
