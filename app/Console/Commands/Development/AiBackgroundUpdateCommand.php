<?php

namespace App\Console\Commands\Development;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Artisan;
use Laravel\Boost\Boost;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'development:ai-background-update')]
class AiBackgroundUpdateCommand extends Command implements PromptsForMissingInput
{
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
     * Execute the console command.
     */
    public function handle(): void
    {
        if (! class_exists(Boost::class)) {
            return;
        }

        $arguments = [
            '--silent' => true,
            '--ansi' => true,
        ];

        if (is_file(base_path('.mcp.json'))) {
            $arguments['--ignore-mcp'] = true;
        }

        // $this->call('boost:install --silent');
        Artisan::call('boost:install', $arguments);
    }
}
