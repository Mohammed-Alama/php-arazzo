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
class MaxParameterCountRule implements Rule
{
    private const MAX_PARAMS = 5;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $params = $node->params;
        $count = count($params);

        if ($count > self::MAX_PARAMS) {
            return [
                RuleErrorBuilder::message(sprintf('Method %s has too many parameters (%d). Maximum allowed is %d.',
                    $node->name->toString(),
                    $count,
                    self::MAX_PARAMS,
                ))->build(),
            ];
        }

        return [];
    }
}
