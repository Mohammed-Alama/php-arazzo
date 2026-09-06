<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class CyclomaticComplexityRule implements Rule
{
    private const MAX_COMPLEXITY = 10;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $complexity = 1; // Base complexity

        if ($node->stmts === null) {
            return [];
        }

        $complexity += $this->calculateComplexity($node->stmts);

        if ($complexity > self::MAX_COMPLEXITY) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Method %s has too high cyclomatic complexity (%d). Maximum allowed is %d.',
                        $node->name->toString(),
                        $complexity,
                        self::MAX_COMPLEXITY,
                    ),
                )->build(),
            ];
        }

        return [];
    }

    private function calculateComplexity(array $nodes): int
    {
        $matches = (new NodeFinder())->find(
            $nodes,
            fn (Node $node): bool => $node instanceof Node\Stmt\If_
                || $node instanceof Node\Stmt\ElseIf_
                || $node instanceof Node\Stmt\For_
                || $node instanceof Node\Stmt\Foreach_
                || $node instanceof Node\Stmt\While_
                || $node instanceof Node\Stmt\Do_
                || $node instanceof Node\Stmt\Switch_
                || $node instanceof Node\Expr\BinaryOp\BooleanAnd
                || $node instanceof Node\Expr\BinaryOp\BooleanOr
                || $node instanceof Node\Expr\Ternary,
        );

        return count($matches);
    }
}
