<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Stmt\Goto_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
class ForbiddenFunctionsRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Eval_) {
            return [
                RuleErrorBuilder::message('Usage of eval() is forbidden.')->build(),
            ];
        }

        if ($node instanceof Goto_) {
            return [
                RuleErrorBuilder::message('Usage of goto is forbidden.')->build(),
            ];
        }

        return [];
    }
}
