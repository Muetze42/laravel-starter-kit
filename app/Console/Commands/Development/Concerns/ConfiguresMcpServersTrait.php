<?php

declare(strict_types=1);

namespace App\Console\Commands\Development\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

trait ConfiguresMcpServersTrait
{
    /**
     * Sets up the MCP servers by invoking necessary configuration methods.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function mcpServers(): void
    {
        $this->context7mcpServer();
    }

    /**
     * Configures the Context7 MCP server across all supported configuration files.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function context7mcpServer(): void
    {
        $key = config('services.context7.key');

        if (! is_string($key) || $key === '') {
            return;
        }

        $trimmedKey = Str::trim($key);

        $this->addContext7ToJsonFiles($trimmedKey);
        $this->addContext7ToCodexConfig($trimmedKey);
    }

    /**
     * Add Context7 MCP server to all JSON-based configuration files.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function addContext7ToJsonFiles(string $apiKey): void
    {
        $serverConfig = [
            'command' => 'npx',
            'args' => ['-y', '@upstash/context7-mcp', '--api-key', $apiKey],
        ];

        foreach ($this->mcpFiles as $relativePath => $serverKey) {
            $file = base_path($relativePath);

            if (! File::exists($file)) {
                continue;
            }

            $data = File::json($file);

            data_set($data, $serverKey . '.context7', $serverConfig);

            File::put($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Add Context7 MCP server to the Codex TOML configuration file.
     *
     * @throws FileNotFoundException
     */
    protected function addContext7ToCodexConfig(string $apiKey): void
    {
        $file = base_path('.codex/config.toml');

        if (! File::exists($file)) {
            return;
        }

        $contents = File::get($file);

        if (Str::contains($contents, '[mcp_servers.context7]')) {
            return;
        }

        $tomlBlock = <<<TOML

            [mcp_servers.context7]
            command = "npx"
            args = ["-y", "@upstash/context7-mcp", "--api-key", "{$apiKey}"]
            TOML;

        File::append($file, $tomlBlock . "\n");
    }
}
