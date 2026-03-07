<?php

namespace App\Console\Commands\Development;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Boost\Boost;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'development:ai-background-update')]
class AiBackgroundUpdateCommand extends Command implements PromptsForMissingInput
{
    /**
     * The MCP configuration files mapped to their server key path.
     *
     * @var array<string, string>
     */
    protected array $mcpFiles = [
        '.mcp.json' => 'mcpServers',
        '.junie/mcp/mcp.json' => 'mcpServers',
        '.ai/mcp/mcp.json' => 'mcpServers',
        '.gemini/settings.json' => 'mcpServers',
        '.vscode/mcp.json' => 'servers',
        '.amp/settings.json' => 'amp.mcpServers',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'development:ai-background-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync AI guidelines and MCP server data';

    /**
     * The Composer instance.
     */
    protected Composer $composer;

    /**
     * The resolved Composer packages from application and global lock files.
     *
     * @var array{app: array<string, string>, global: array<string, string>}
     */
    protected array $composerPackages = [
        'app' => [],
        'global' => [],
    ];

    /**
     * Execute the console command.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function handle(Composer $composer): void
    {
        if (! class_exists(Boost::class)) {
            return;
        }

        $this->composer = $composer;
        $this->composer->setWorkingPath(base_path());

        $this->resolveComposerPackages();
        $this->addAnalysisAndSecurityChecks();
        $this->runningBoost();
    }

    /**
     * Run Boost update and configure MCP servers.
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    protected function runningBoost(): void
    {
        if (app()->isProduction() && ! File::exists(base_path('.env'))) {
            $this->components->warn('Boost cannot run because the .env file is missing.');

            return;
        }

        $this->call('boost:update');
        $this->mcpServers();
    }

    /**
     * Generate the analysis and security guidelines from the stub.
     *
     * @throws FileNotFoundException
     */
    protected function addAnalysisAndSecurityChecks(): void
    {
        $stub = base_path('stubs/.ai/analysis-and-security.stub');

        $contents = $this->getStubContents($stub);

        if ($contents === false) {
            return;
        }

        $i = 1;
        $checks = [];

        $rector = $this->hasAnyApplicationComposerPackage('rector/rector');
        $phpStan = $this->hasAnyApplicationComposerPackage(['phpstan/phpstan', 'larastan/larastan']);

        if ($rector) {
            $checks[] = $i++ . '`vendor/bin/rector` - Automated refactoring and code upgrades';
        }

        if ($phpStan) {
            $checks[] = $i++ . '`vendor/bin/phpstan analyse --error-format=json` - Static analysis to catch type errors and bugs';
        }

        if ($this->hasAnyApplicationComposerPackage('laravel/pint')) {
            $checks[] = $i++ . '`vendor/bin/pint --dirty` - Final code formatting (Rector changes need reformatting)';
        }

        if ($this->hasAnyComposerPackage('ai-provide/warden')) {
            $checks[] = $i . '`ai-warden check app --format=json` - Detect AI slop patterns and AI-generated anti-patterns';
        }

        if ($checks === []) {
            $this->components->warn('No analysis and security tools found. Do not use this app productively or publish the code.');

            return;
        }

        $checks = array_map(static fn (string $check): string => '    ' . $check, $checks);

        if ($rector) {
            $checks[] = '- Rector automatically applies modern PHP patterns and Laravel best practices.';
        }

        if ($phpStan && $modeContent = $this->getStubContents(base_path('stubs/.ai/analysis-and-security/phpstan.stub'))) {
            $checks[] = "\n" . $modeContent;
        }

        $contents = str_replace(['{{checks}}', '{{ checks }}'], implode("\n", $checks), $contents);

        File::put(base_path('.ai/guidelines/analysis-and-security.md'), $contents . "\n");
    }

    /**
     * Read and return the trimmed contents of a stub file.
     *
     * @throws FileNotFoundException
     */
    protected function getStubContents(string $stub): string|false
    {
        if (! File::exists($stub)) {
            $this->components->warn(sprintf('Stub %s not found.', $stub));

            return false;
        }

        $contents = trim(File::get($stub));

        if ($contents === '') {
            return false;
        }

        return $contents;
    }

    /**
     * Determine if any of the given packages are installed in the application.
     *
     * @param  string[]|string  $packages
     */
    protected function hasAnyApplicationComposerPackage(array|string $packages): bool
    {
        if (! is_array($packages)) {
            $packages = [$packages];
        }

        if ($this->composerPackages['app'] === []) {
            return array_filter(array_map($this->composer->hasPackage(...), $packages)) !== [];
        }

        return array_intersect_key($this->composerPackages['app'], array_flip($packages)) !== [];
    }

    /**
     * Determine if any of the given packages are installed locally or globally.
     *
     * @param  string[]|string  $packages
     */
    protected function hasAnyComposerPackage(array|string $packages): bool
    {
        if (! is_array($packages)) {
            $packages = [$packages];
        }

        if ($this->hasAnyApplicationComposerPackage($packages)) {
            return true;
        }

        return array_intersect_key($this->composerPackages['global'], array_flip($packages)) !== [];
    }

    /**
     * Resolve packages from both application and global Composer lock files.
     *
     * @throws FileNotFoundException
     */
    protected function resolveComposerPackages(): void
    {
        $this->resolveGlobalComposerPackages();
        $this->resolveApplicationComposerPackages();
    }

    /**
     * Resolve packages from the application's composer.lock file.
     *
     * @throws FileNotFoundException
     */
    protected function resolveApplicationComposerPackages(): void
    {
        $appComposerLockFile = base_path('composer.lock');

        if (! File::exists($appComposerLockFile)) {
            $this->components->info('Application composer.lock file does not exist.');

            return;
        }

        $this->composerPackages['app'] = $this->getComposerLockPackages($appComposerLockFile);
    }

    /**
     * Resolve packages from the global Composer lock file.
     *
     * @throws FileNotFoundException
     */
    protected function resolveGlobalComposerPackages(): void
    {
        $composerBinary = $this->findComposer();

        $composerHome = Str::trim(Process::run([...$composerBinary, 'config', '--global', 'home'])->output());

        $globalComposerLockFile = $composerHome . '/composer.lock';

        if (! File::exists($globalComposerLockFile)) {
            $this->components->warn('Could not locate global composer lock file.');

            return;
        }

        $this->composerPackages['global'] = $this->getComposerLockPackages($globalComposerLockFile);
    }

    /**
     * Extract package names and versions from a composer.lock file.
     *
     * @return array<string, string>
     *
     * @throws FileNotFoundException
     */
    protected function getComposerLockPackages(string $lockFile): array
    {
        $lockData = $this->getComposerLockData($lockFile);

        return array_merge(
            collect($lockData['packages'])->pluck('version', 'name')->all(),
            collect($lockData['packages-dev'])->pluck('version', 'name')->all(),
        );
    }

    /**
     * Parse the raw JSON data from a composer.lock file.
     *
     * @return array{
     *     packages: array<int, array{name: string, version: string}>,
     *     packages-dev: array<int, array{name: string, version: string}>
     * }
     *
     * @throws FileNotFoundException
     */
    protected function getComposerLockData(string $lockFile): array
    {
        /** @var array{packages: array<int, array{name: string, version: string}>, packages-dev: array<int, array{name: string, version: string}>} */
        return File::json($lockFile);
    }

    /**
     * Resolve the Composer binary path.
     *
     * @return string[]
     */
    protected function findComposer(): array
    {
        $boostComposerExecutable = config('boost.executable_paths.composer');

        if (is_string($boostComposerExecutable) && Str::trim($boostComposerExecutable) !== '') {
            return [$boostComposerExecutable];
        }

        return $this->composer->findComposer();
    }

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
