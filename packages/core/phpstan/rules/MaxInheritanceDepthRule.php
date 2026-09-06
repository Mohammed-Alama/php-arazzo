<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
class MaxInheritanceDepthRule implements Rule
{
    private const MAX_DEPTH = 5;

    public function __construct(private ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->extends === null) {
            return [];
        }

        $depth = 0;

        // We use the reflection provider to traverse the hierarchy
        // because $node only has the immediate parent.
        $className = (string) $node->namespacedName;
        $reflection = $this->reflectionProvider->getClass($className);

        while ($reflection->getParentClass()) {
            $depth++;
            $reflection = $reflection->getParentClass();
        }

        if ($depth > self::MAX_DEPTH) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Class %s has too deep inheritance depth (%d). Maximum allowed is %d.',
                        $className,
                        $depth,
                        self::MAX_DEPTH,
                    ),
                )->build(),
            ];
        }

        return [];
    }
}
