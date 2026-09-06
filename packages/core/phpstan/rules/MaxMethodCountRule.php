<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
class MaxMethodCountRule implements Rule
{
    private const MAX_METHODS = 20;

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $methodCount = 0;
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $methodCount++;
            }
        }

        if ($methodCount > self::MAX_METHODS) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Class %s has too many methods (%d). Maximum allowed is %d.',
                        $node->name->toString(),
                        $methodCount,
                        self::MAX_METHODS,
                    ),
                )->build(),
            ];
        }

        return [];
    }
}
