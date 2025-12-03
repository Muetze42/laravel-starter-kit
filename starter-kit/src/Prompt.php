<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace StarterKit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

/**
 * @SuppressWarnings(PHPMD)
 */
class Prompt
{
    /**
     * The composer packages to install.
     *
     * @var string[]
     */
    protected array $composerPackages = [];

    /**
     * The composer dev packages to install.
     *
     * @var string[]
     */
    protected array $composerDevPackages = [];

    /**
     * @var array<string|string>
     */
    protected array $versions = [
        'laravel/sanctum' => '^4.0',
        'laravel/wayfinder' => '^0.1.5',
        'laravel/horizon' => '^5.32',
        'spatie/laravel-ray' => '^1.4',
        'inertiajs/inertia-laravel' => '^2.0',
    ];

    protected Filesystem $filesystem;

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    #[NoReturn]
    public function __construct()
    {
        $this->filesystem = new Filesystem();

        $this->configurePackages();
        $this->updateComposerJson();
        $this->handleMoreFiles();

        if (! confirm('Keep the Starter Kit files?', default: false)) {
            $this->filesystem->deleteDirectory(base_path('starter-kit'));
        }

        // $this->filesystem->ensureDirectoryExists(base_path('starter-kit'));

        foreach ([base_path('starter-kit/vendor'), base_path('starter-kit/src')] as $path) {
            $this->filesystem->deleteDirectory($path);
        }

        $this->filesystem->delete(base_path('kit.php'));
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function handleMoreFiles(): void
    {
        if (in_array('laravel/sanctum', $this->composerPackages)) {
            $this->publishConfig('horizon');
            $this->publishProvider('HorizonServiceProvider');
            $path = base_path('bootstrap/providers.php');
            $contents = $this->filesystem->get($path);
            $contents = str_replace(
                ']',
                "    App\Providers\HorizonServiceProvider::class,\n]",
                $contents
            );
            $this->filesystem->put($path, $contents);
        }
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function updateComposerJson(): void
    {
        $data = $this->filesystem->json(base_path('composer.json'));
        $data = $this->addComposerJson($data, $this->composerPackages);
        $data = $this->addComposerJson($data, $this->composerDevPackages, 'require-dev');

        $data['scripts']['post-root-package-install'] = Arr::except(
            $data['scripts']['post-root-package-install'],
            '@php ./kit.php --ansi'
        );
        $data['scripts']['post-root-package-install'] = Arr::take(
            $data['scripts']['post-root-package-install'],
            count($data['scripts']['post-root-package-install']) - 1
        );

        /** @noinspection JsonEncodingApiUsageInspection */
        $this->filesystem->put(
            base_path('composer.json'),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    protected function addComposerJson(array $data, array $packages, string $key = 'require'): array
    {
        foreach ($packages as $package) {
            $data[$key][$package] = $this->versions[$package] ?? '*';
        }

        return $data;
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function configurePackages(): void
    {
        $optionsDefault = [
            'ray' => 'Ray',
        ];
        $options = [
            'laravel/sanctum' => 'Laravel Sanctum',
            'laravel/horizon' => 'Laravel Horizon', // @Todo
            // 'laravel/nova' => 'Laravel Nova', // @Todo
            // 'laravel/pulse' => 'Laravel Pulse', // @Todo
            // 'laravel/reverb' => 'Laravel Reverb', // @Todo
            // 'laravel/scout' => 'Laravel Scout', // @Todo
            // 'laravel/socialite' => 'Laravel Socialite', // @Todo
            // 'laravel/telescope' => 'Laravel Telescope', // @Todo
            // 'spatie/laravel-activitylog' => 'Laravel Activity Log', // @Todo
            // 'spatie/laravel-backup' => 'Laravel Backup', // @Todo
            // 'spatie/laravel-medialibrary' => 'Laravel Media Library', // @Todo
            // 'spatie/laravel-permission' => 'Laravel Permission', // @Todo
            // 'spatie/laravel-translatable' => 'Eloquent models translatable (Spatie)', // @Todo
        ];

        if (confirm('Setup the Vue.js frontend?')) {
            // $options['laravel/wayfinder'] = 'Laravel Wayfinder';
            // $default[] = 'laravel/wayfinder';
            $this->scaffoldingFrontend();
            $this->composerPackages[] = 'inertiajs/inertia-laravel';
        }

        $packages = multiselect(
            label: 'Wich packages should be install?',
            options: array_merge($optionsDefault, $options),
            default: array_keys($optionsDefault),
        );

        $this->composerPackages = array_merge(
            $this->composerPackages,
            Arr::where($packages, fn (string $package) => Str::contains($package, '/'))
        );

        $this->wayfinder();
        $this->sanctum();
        if (in_array('ray', $packages)) {
            $this->ray();
        }
    }

    protected function scaffoldingFrontend(): void
    {
        $this->filesystem->copyDirectory(
            base_path('starter-kit/frontend'),
            base_path('')
        );
        $this->filesystem->delete(resource_path('js/app.js'));
        $this->filesystem->delete(resource_path('js/bootstrap.js'));
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function wayfinder(): void
    {
        if (! in_array('laravel/wayfinder', $this->composerPackages)) {
            return;
        }

        $path = base_path('vite.config.js');
        $this->filesystem->put($path, str_replace('// ', '', $this->filesystem->get($path)));
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function sanctum(): void
    {
        if (! in_array('laravel/sanctum', $this->composerPackages)) {
            return;
        }

        foreach (['.env', '.env.example'] as $item) {
            $path = base_path($item);
            $this->filesystem->put($path, str_replace('#SANCTUM', 'SANCTUM', $this->filesystem->get($path)));
        }

        $path = app_path('Models/User.php');
        $contents = $this->filesystem->get($path);
        $contents = str_replace(
            [
                'use Illuminate\Notifications\Notifiable;',
                'use Notifiable;',
            ],
            [
                "use Illuminate\Notifications\Notifiable;\nuse Laravel\Sanctum\HasApiTokens;",
                "use HasApiTokens;\n    use Notifiable;",
            ],
            $contents
        );
        $this->filesystem->put($path, $contents);
    }

    protected function ray(): void
    {
        if (confirm('Install Ray into the production environment?', default: false)) {
            $this->composerPackages[] = 'spatie/laravel-ray';

            return;
        }

        $this->composerDevPackages[] = 'spatie/laravel-ray';
    }

    protected function publishConfig(string $file): void
    {
        $this->publishFile($file);
    }

    protected function publishProvider(string $file): void
    {
        $this->publishFile($file, 'providers', 'app/Providers');
    }

    protected function publishFile(string $file, string $sourceDir = 'configs', string $targetDir = 'config'): void
    {
        $this->filesystem->copy(
            'starter-kit/' . $sourceDir . '/' . $file . '.stub',
            $targetDir . '/' . $file . '.php',
        );
    }
}
