<?php

declare(strict_types=1);

namespace PHPStanRules\Rules;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces PSR-style naming conventions for structural declarations.
 *
 * @implements Rule<Node>
 */
class PsrNamingConventionRule implements Rule
{
    /**
     * Get the analyzed node type.
     */
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * Process a parsed node.
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Interface_) {
            return $this->validateSuffix($node, $scope, 'Interface', 'Interfaces must be suffixed by Interface.');
        }

        if ($node instanceof Trait_) {
            return $this->validateSuffix($node, $scope, 'Trait', 'Traits must be suffixed by Trait.');
        }

        if ($node instanceof Class_ && $node->isAbstract()) {
            return $this->validateAbstractClass($node, $scope);
        }

        return [];
    }

    /**
     * Validate an abstract class declaration.
     *
     * @return list<RuleError>
     */
    protected function validateAbstractClass(Class_ $node, Scope $scope): array
    {
        $className = $node->name?->toString();

        if ($className === null) {
            return [];
        }

        if (Str::startsWith($className, 'Abstract')) {
            return [];
        }

        return [
            $this->buildError($node, $scope, 'Abstract classes must be prefixed by Abstract.'),
        ];
    }

    /**
     * Validate a declaration suffix.
     *
     * @return list<RuleError>
     */
    protected function validateSuffix(Interface_|Trait_ $node, Scope $scope, string $suffix, string $message): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $declarationName = $node->name->toString();

        if (Str::endsWith($declarationName, $suffix)) {
            return [];
        }

        return [
            $this->buildError($node, $scope, $message),
        ];
    }

    /**
     * Build a PHPStan rule error.
     */
    protected function buildError(Node $node, Scope $scope, string $message): RuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('psr.namingConvention')
            ->line($node->getStartLine())
            ->file($scope->getFile(), $scope->getFileDescription())
            ->build();
    }
}
