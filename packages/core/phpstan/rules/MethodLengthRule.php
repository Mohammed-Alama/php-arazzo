<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class MethodLengthRule implements Rule
{
    private const MAX_LENGTH = 30;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->stmts === null) {
            return [];
        }

        $length = 0;
        foreach ($node->stmts as $stmt) {
            $length++;
        }

        if ($length > self::MAX_LENGTH) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Method %s is too long (%d statements). Maximum allowed is %d.', $node->name->toString(), $length, self::MAX_LENGTH),
                )->build(),
            ];
        }

        return [];
    }
}
