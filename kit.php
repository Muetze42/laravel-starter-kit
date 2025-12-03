<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

/** @noinspection AutoloadingIssuesInspection */

require_once __DIR__ . '/starter-kit/vendor/autoload.php';

use StarterKit\Prompt;

use function Illuminate\Filesystem\join_paths;

function base_path(string $path = ''): string
{
    return join_paths(dirname(__FILE__), $path);
}
function resource_path(string $path = ''): string
{
    return join_paths(dirname(__FILE__), 'resources', $path);
}
function app_path(string $path = ''): string
{
    return join_paths(dirname(__FILE__), 'app', $path);
}

new Prompt();
