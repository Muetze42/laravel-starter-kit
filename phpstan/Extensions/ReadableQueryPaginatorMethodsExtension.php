<?php

declare(strict_types=1);

namespace PHPStanRules\Extensions;

use App\Support\Pagination\ReadableQueryCursorPaginator;
use App\Support\Pagination\ReadableQueryLengthAwarePaginator;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;

class ReadableQueryPaginatorMethodsExtension implements MethodsClassReflectionExtension
{
    /**
     * Create the paginator method extension.
     */
    public function __construct(protected ReflectionProvider $reflectionProvider) {}

    /**
     * Determine whether the paginator exposes a readable URL method.
     */
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($methodName !== 'withReadableQueryUrls') {
            return false;
        }

        if ($classReflection->getName() === LengthAwarePaginator::class) {
            return true;
        }

        if ($classReflection->getName() === CursorPaginator::class) {
            return true;
        }

        if ($classReflection->isSubclassOf(LengthAwarePaginator::class)) {
            return true;
        }

        return $classReflection->isSubclassOf(CursorPaginator::class);
    }

    /**
     * Get the reflected readable URL method.
     */
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        if ($classReflection->getName() === CursorPaginator::class || $classReflection->isSubclassOf(CursorPaginator::class)) {
            return $this->reflectionProvider
                ->getClass(ReadableQueryCursorPaginator::class)
                ->getMethod($methodName, new OutOfClassScope());
        }

        return $this->reflectionProvider
            ->getClass(ReadableQueryLengthAwarePaginator::class)
            ->getMethod($methodName, new OutOfClassScope());
    }
}
