<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use App\Services\Scaffolding\Contracts\PresetInterface;
use JsonException;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

class ConfigurationEditor
{
    /**
     * Inject parser process and file access.
     */
    public function __construct(protected ProcessRunner $runner)
    {
        //
    }

    /**
     * Compute JavaScript edits without executing user configuration.
     *
     * @return array<string, string|null>
     *
     * @throws JsonException
     */
    public function javascript(string $root, PresetInterface $preset): array
    {
        $output = $this->runner->run(['node', base_path('stubs/frontend/inspect.mjs'), json_encode([
            'entry' => 'resources/js/app.' . $preset->entryExtension(),
            'pluginImport' => $preset->pluginImport(),
            'pluginExpression' => $preset->pluginExpression(),
        ], JSON_THROW_ON_ERROR)], $root);
        $changes = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($changes) || $changes === []) {
            throw new RuntimeException('Could not determine a safe JavaScript migration.');
        }

        foreach ($changes as $path => $contents) {
            if (! is_string($path) || (! is_string($contents) && $contents !== null)) {
                throw new RuntimeException('Invalid JavaScript migration result.');
            }
        }

        return $changes;
    }

    /**
     * Append middleware inside the existing configuration closure using parsed offsets.
     */
    public function middleware(string $source): string
    {
        $tree = new ParserFactory()->createForNewestSupportedVersion()->parse($source) ?? [];
        $finder = new NodeFinder();
        $calls = array_values(array_filter($finder->findInstanceOf($tree, MethodCall::class),
            fn (MethodCall $call): bool => $call->name instanceof Identifier
            && $call->name->name === 'withMiddleware'));
        $closure = count($calls) === 1 ? ($calls[0]->getArgs()[0]->value ?? null) : null;
        if (! $closure instanceof Closure || count($closure->params) !== 1
            || ! $closure->params[0]->var instanceof Variable || ! is_string($closure->params[0]->var->name)
            || $finder->findInstanceOf($closure->stmts, Return_::class) !== []) {
            throw new RuntimeException(
                'bootstrap/app.php: use one withMiddleware closure without early returns ' . 'before installing.');
        }

        $variable = $closure->params[0]->var->name;
        // Parser offsets are bytes, so multibyte string helpers would corrupt non-ASCII source.
        $offset = $closure->getEndFilePos();
        $source = substr_replace($source,
            "    \${$variable}->web(append: [HandleInertiaRequests::class]);\n    ", $offset, 0);
        $tokens = token_get_all($source);
        $position = 0;
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_USE) {
                return substr_replace($source, "use App\\Http\\Middleware\\HandleInertiaRequests;\n", $position, 0);
            }

            $position += strlen(is_array($token) ? $token[1] : $token);
        }

        throw new RuntimeException('bootstrap/app.php: expected top-level imports.');
    }
}
