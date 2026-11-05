<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Closure;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ProcessRunner
{
    /**
     * Run a bounded process without shell interpolation.
     *
     * @param  list<string>  $command
     * @param  Closure(string): void|null  $output
     */
    public function run(array $command, string $directory, ?Closure $output = null, int $timeout = 600): string
    {
        $result = Process::path($directory)->timeout($timeout)->run($command, function (string $type,
            string $buffer) use ($output): void {
            $output?->__invoke($buffer);
        });

        if ($result->failed()) {
            throw new RuntimeException(implode(' ', $command) . " failed.\n" . $result->errorOutput()
                . $result->output());
        }

        return $result->output();
    }
}
