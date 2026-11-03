<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Condition\Ast\Accessor;
use Alama\Arazzo\Evaluation\Condition\Ast\Index;
use Alama\Arazzo\Evaluation\Condition\Ast\Member;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

/**
 * Splits a captured simple-condition runtime-expression operand into the
 * longest valid runtime-expression prefix and its trailing accessor chain.
 *
 * The runtime-expression grammar has no accessor syntax, while the Criterion
 * Object Operators table allows `[]` index and `.` property de-reference on a
 * runtime-expression operand. The expression parser remains the authority on
 * what a valid base is.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class AccessorParser
{
    private ExpressionParser $expressionParser;

    public function __construct()
    {
        $this->expressionParser = new ExpressionParser();
    }

    /**
     * @return array{string, list<Accessor>}
     */
    public function split(string $captured): array
    {
        $bare = self::bareExpression($captured);
        $length = strlen($bare);

        $cuts = [];
        for ($i = 1; $i < $length; $i++) {
            if ($bare[$i] === '.' || $bare[$i] === '[') {
                $cuts[] = $i;
            }
        }
        $cuts[] = $length;

        foreach (array_reverse($cuts) as $cut) {
            $prefix = substr($bare, 0, $cut);
            if ($prefix === '' || !str_starts_with($prefix, '$') || !$this->isExpression($prefix)) {
                continue;
            }

            return [$prefix, $this->parseAccessors(substr($bare, $cut))];
        }

        throw new ConditionSyntaxException("Malformed runtime expression in condition: {$captured}.");
    }

    private function isExpression(string $candidate): bool
    {
        try {
            $this->expressionParser->parse($candidate);

            return true;
        } catch (ExpressionSyntaxException) {
            return false;
        }
    }

    /**
     * @return list<Accessor>
     */
    private function parseAccessors(string $rest): array
    {
        $accessors = [];
        $length = strlen($rest);
        $i = 0;

        while ($i < $length) {
            $remaining = substr($rest, $i);

            if ($rest[$i] === '.' && preg_match('/^\.([A-Za-z0-9_\-~%@+:]+)/', $remaining, $m) === 1) {
                $accessors[] = new Member($m[1]);
                $i += strlen($m[0]);

                continue;
            }

            if ($rest[$i] === '[' && preg_match('/^\[(\d+)\]/', $remaining, $m) === 1) {
                $accessors[] = new Index((int) $m[1]);
                $i += strlen($m[0]);

                continue;
            }

            throw new ConditionSyntaxException("Malformed accessor in condition runtime expression: {$rest}.");
        }

        return $accessors;
    }

    /**
     * The lexer captures runtime expressions verbatim: bare `$...`, the inner
     * content of `{$...}`, or the alternate `${...}` spelling including its
     * closing brace. Normalize all three to the bare `$...` form.
     */
    private static function bareExpression(string $captured): string
    {
        $body = str_ends_with($captured, '}') ? substr($captured, 0, -1) : $captured;

        if (str_starts_with($body, '${')) {
            $body = '$'.substr($body, 2);
        }

        return $body;
    }
}
