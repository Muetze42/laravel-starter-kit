<?php

declare(strict_types=1);

namespace App\Console\Commands\Development\Overrides;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Console\ServeCommand as Command;
use Illuminate\Support\Str;
use Laravel\AgentDetector\AgentDetector;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'serve')]
class ServeCommand extends Command
{
    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    #[Override]
    public function handle(): int
    {
        if (! $this->hasServeableAppUrl()) {
            $this->error('This application is not suitable for serving on the PHP development server');

            return self::FAILURE;
        }

        if (AgentDetector::detect()->isAgent) {
            $this->error('This command must not be executed by AI agents');

            return self::FAILURE;
        }

        return parent::handle();
    }

    /**
     * Determines if the application URL is serveable based on specified criteria.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function hasServeableAppUrl(): bool
    {
        $url = $this->laravel->make(Repository::class)->get('app.url');

        return Str::isUrl($url, ['http', 'https'])
            && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            && in_array(parse_url($url, PHP_URL_PORT), range(1024, 65535), true)
            && in_array(parse_url($url, PHP_URL_PATH), [null, '', '/'], true)
            && parse_url($url, PHP_URL_QUERY) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null;
    }
}
