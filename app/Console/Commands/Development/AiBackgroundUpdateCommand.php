<?php

declare(strict_types=1);

namespace App\Console\Commands\Development;

use App\Console\Commands\Development\Concerns\ConfiguresMcpServersTrait;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Boost\Boost;
use Laravel\Boost\Install\ThirdPartyPackage;
use Laravel\Roster\ProjectManager;
use Symfony\Component\Console\Attribute\AsCommand;

use function Illuminate\Filesystem\join_paths;

#[AsCommand(name: 'development:ai-background-update')]
class AiBackgroundUpdateCommand extends Command implements PromptsForMissingInput
{
    use ConfiguresMcpServersTrait;

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
     * The config context files to customize.
     *
     * @var list<string>
     */
    protected array $agentsMarkdownFiles = [
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
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
     * Guidelines mapped to the packages that trigger their generation.
     *
     * @var array{
     *     flux-ui: array{'livewire/flux', 'livewire/flux-pro'},
     *     livewire: array{'livewire/livewire'}
     * }
     */
    protected array $packageGuidelines = [
        'flux-ui' => ['livewire/flux', 'livewire/flux-pro'],
        'livewire' => ['livewire/livewire'],
    ];

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
     * @throws \JsonException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(Composer $composer): void
    {
        if (! $this->shouldRunCommand()) {
            return;
        }

        $project = resolve(ProjectManager::class);
        $this->composer = $composer;
        $this->composer->setWorkingPath(base_path());

        $this->resolveComposerPackages();

        File::ensureDirectoryExists($this->targetDirectory());

        $this->addAnalysisAndSecurityChecks();
        $this->resolvePackageGuidelines();
        $this->resolveBoostPackageGuidelines($project);
        $this->runningBoost();
    }

    /**
     * Determine whether the command should run based on environment prerequisites.
     */
    protected function shouldRunCommand(): bool
    {
        if (! class_exists(Boost::class)) {
            return false;
        }

        if (! File::exists(base_path('.env'))) {
            $this->components->warn('The .env file is missing. Please create it before running this command.');

            return false;
        }

        if (! config('app.key')) {
            $this->components->warn('The application key is missing. Please generate it before running this command.');

            return false;
        }

        if (! app()->isLocal()) {
            $this->comment('Skip AI background command for this environment.');

            return false;
        }

        return true;
    }

    /**
     * Sync third-party packages and their skills in boost.json.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     */
    protected function resolveBoostPackageGuidelines(ProjectManager $project): void
    {
        $file = base_path('boost.json');

        if (! File::exists($file)) {
            return;
        }

        $data = File::json($file);
        /** @var string[] $packages */
        $packages = data_get($data, 'packages', []);
        /** @var string[] $skills */
        $skills = data_get($data, 'skills', []);
        $initPackages = $packages;
        $initSkills = $skills;

        $discoveredPackages = ThirdPartyPackage::discover($project);

        /** @var string[] $excludedGuidelines */
        $excludedGuidelines = config('boost.guidelines.exclude', []);

        $packages = $discoveredPackages
            ->reject(fn (ThirdPartyPackage $pkg, string $name): bool => in_array($name, $excludedGuidelines, true))
            ->keys()
            ->values()
            ->all();

        /** @var string[] $excludedSkills */
        $excludedSkills = config('boost.skills.exclude', []);

        $skills = $discoveredPackages
            ->reject(fn (ThirdPartyPackage $pkg, string $name): bool => in_array($name, $excludedGuidelines, true))
            ->filter(fn (ThirdPartyPackage $pkg): bool => $pkg->hasSkills)
            ->flatMap(fn (ThirdPartyPackage $pkg): array => $this->discoverPackageSkills($pkg->name))
            ->reject(fn (string $skill): bool => in_array($skill, $excludedSkills, true))
            ->values()
            ->all();

        sort($packages);
        sort($skills);

        if ($packages === $initPackages && $skills === $initSkills) {
            return;
        }

        data_set($data, 'packages', $packages);
        data_set($data, 'skills', $skills);
        File::put($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Discover skill names from a package's Boost skills directory.
     *
     * @return string[]
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function discoverPackageSkills(string $package): array
    {
        $skillsPath = base_path(sprintf('vendor/%s/resources/boost/skills', $package));
        if (! File::isDirectory($skillsPath)) {
            return [];
        }

        $skills = [];
        foreach (File::directories($skillsPath) as $skillDir) {
            if (! is_string($skillDir)) {
                continue;
            }

            foreach (['SKILL.blade.php', 'SKILL.md'] as $filename) {
                $skillFile = $skillDir . '/' . $filename;
                if (! File::exists($skillFile)) {
                    continue;
                }

                $content = File::get($skillFile);

                if (
                    preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)
                    && preg_match('/^name:\s*(.+)$/m', $matches[1], $nameMatch)
                ) {
                    $skills[] = Str::trim($nameMatch[1]);
                }

                break;
            }
        }

        return $skills;
    }

    /**
     * Sync package-specific guideline files based on installed dependencies.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function resolvePackageGuidelines(): void
    {
        foreach ($this->packageGuidelines as $guideline => $packages) {
            $this->packageGuideline($guideline, $packages);
        }
    }

    /** Get the target directory path for generated guideline files. */
    protected function targetDirectory(string $path = ''): string
    {
        return join_paths(base_path('.ai/guidelines'), $path);
    }

    /**
     * Run Boost update and configure MCP servers.
     *
     * @throws \JsonException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function runningBoost(): void
    {
        $this->call('boost:install', [
            '--no-interaction' => true,
            '--guidelines' => true,
            '--skills' => true,
            '--mcp' => true,
        ]);
        $this->mcpServers();
        $this->updateAgentMarkdownFiles();
    }

    /**
     * Replace the generated build and dev command hint with the preferred pnpm dev command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function updateAgentMarkdownFiles(): void
    {
        $search = '`pnpm run build`, `pnpm run dev`, or `composer run dev`';
        $replace = '`pnpm run dev`';

        foreach ($this->agentsMarkdownFiles as $agentMarkdownFile) {
            $file = base_path($agentMarkdownFile);

            if (! File::exists($file)) {
                continue;
            }

            $contents = File::get($file);
            $updatedContents = str_replace($search, $replace, $contents);

            if ($contents === $updatedContents) {
                continue;
            }

            File::put($file, $updatedContents);
        }
    }

    /**
     * Generate the analysis and security guidelines from the stub.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function addAnalysisAndSecurityChecks(): void
    {
        $contents = $this->getStubContents('analysis-and-security.stub');

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
            $checks[] = $i++ . '`vendor/bin/phpstan analyse --error-format=json`' .
                ' - Static analysis to catch type errors and bugs';
        }

        if ($this->hasAnyApplicationComposerPackage('laravel/pint')) {
            $checks[] = $i++ . '`vendor/bin/pint --dirty` - Final code formatting (Rector changes need reformatting)';
        }

        if ($this->hasAnyComposerPackage('ai-provide/warden')) {
            $checks[] = $i . '`ai-warden check app --format=json` ' .
                '- Detect AI slop patterns and AI-generated anti-patterns';
        }

        if ($checks === []) {
            $this->components->warn('No analysis and security tools found.' .
                ' Do not use this app productively or publish the code.');

            return;
        }

        $checks = array_map(static fn (string $check): string => '    ' . $check, $checks);

        if ($rector) {
            $checks[] = '- Rector automatically applies modern PHP patterns and Laravel best practices.';
        }

        if ($phpStan && $modeContent = $this->getStubContents('analysis-and-security/phpstan.stub')) {
            $checks[] = "\n" . $modeContent;
        }

        $contents = str_replace(['{{checks}}', '{{ checks }}'], implode("\n", $checks), $contents);

        File::put($this->targetDirectory('analysis-and-security.md'), $contents . "\n");
    }

    /**
     * Read and return the trimmed contents of a stub file.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getStubContents(string $stub): string|false
    {
        if (! str_ends_with($stub, '.stub')) {
            $stub .= '.stub';
        }

        if (! File::exists($stub)) {
            $stub = base_path(join_paths('stubs/.ai', $stub));
        }

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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function resolveComposerPackages(): void
    {
        $this->resolveGlobalComposerPackages();
        $this->resolveApplicationComposerPackages();
    }

    /**
     * Resolve packages from the application's composer.lock file.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getComposerLockPackages(string $lockFile): array
    {
        $lockData = $this->getComposerLockData($lockFile);
        $packages = [];

        foreach (array_merge($lockData['packages'], $lockData['packages-dev']) as $package) {
            $packages[$package['name']] = $package['version'];
        }

        return $packages;
    }

    /**
     * Parse the raw JSON data from a composer.lock file.
     *
     * @return array{
     *     packages: array<int, array{name: string, version: string}>,
     *     packages-dev: array<int, array{name: string, version: string}>
     * }
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
     * Create or remove a guideline file based on whether its packages are installed.
     *
     * @param  string|string[]  $packages
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function packageGuideline(string $guideline, string|array $packages): void
    {
        $filename = pathinfo($guideline, PATHINFO_FILENAME);
        $target = $this->targetDirectory($filename . '.md');

        if (! $this->hasAnyApplicationComposerPackage($packages)) {
            if (File::exists($target)) {
                File::delete($target);
            }

            return;
        }

        if ($contents = $this->getStubContents($filename)) {
            File::put($target, $contents);
        }
    }
}
