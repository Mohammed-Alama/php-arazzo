<?php

declare(strict_types=1);

namespace Arazzo\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
class IdentifierLengthRule implements Rule
{
    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 30;

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof ClassMethod) {
            return $this->checkLength($node->name->toString(), 'Method');
        }

        if ($node instanceof Property) {
            $errors = [];
            foreach ($node->props as $prop) {
                $name = $prop->name->toString();
                $len = strlen($name);
                if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf("Property name '%s' length (%d) is outside allowed range (%d-%d).",
                            $name,
                            $len,
                            self::MIN_LENGTH,
                            self::MAX_LENGTH,
                        ),
                    )->build();
                }
            }

            return $errors;
        }

        return [];
    }

    private function checkLength(string $name, string $type): array
    {
        $len = strlen($name);
        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            return [
                RuleErrorBuilder::message(
                    sprintf("%s name '%s' length (%d) is outside allowed range (%d-%d).",
                        $type,
                        $name,
                        $len,
                        self::MIN_LENGTH,
                        self::MAX_LENGTH,
                    ),
                )->build(),
            ];
        }

        return [];
    }
}
