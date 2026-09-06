<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class BooleanMethodNamingRule implements Rule
{
    private const BOOLEAN_PREFIXES = ['is', 'has', 'can', 'should', 'will'];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $methodName = $node->name->toString();
        $returnType = $node->getReturnType();

        // We only care about methods that explicitly return bool or are suspected booleans
        // If return type is not specified, we check if the name suggests a boolean
        $isBooleanReturn = $this->returnsBool($returnType);

        // If it looks like a boolean method (starts with is/has/can/should/will)
        // but isn't explicitly typed as bool, or vice versa.
        $startsWithBooleanPrefix = false;
        foreach (self::BOOLEAN_PREFIXES as $prefix) {
            if (!str_starts_with($methodName, $prefix)) {
                continue;
            }

            $nextChar = $methodName[strlen($prefix)] ?? null;
            if ($nextChar === null || ctype_upper($nextChar)) {
                $startsWithBooleanPrefix = true;
                break;
            }
        }

        if ($startsWithBooleanPrefix && !$isBooleanReturn) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Method %s starts with a boolean prefix but does not explicitly return bool.', $methodName),
                )->build(),
            ];
        }

        return [];
    }

    private function returnsBool(?Node $returnType): bool
    {
        if ($returnType === null) {
            return false;
        }

        $types = match (true) {
            $returnType instanceof NullableType => [$returnType->type],
            $returnType instanceof UnionType, $returnType instanceof IntersectionType => $returnType->types,
            default => [$returnType],
        };

        foreach ($types as $type) {
            if (str_ends_with($type->toString(), 'bool')) {
                return true;
            }
        }

        return false;
    }
}
