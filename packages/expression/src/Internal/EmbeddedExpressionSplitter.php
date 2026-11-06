<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Internal;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Expression\Data\EmbeddedSegment;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser;

/**
 * Grammar-aware splitter for Arazzo embedded expressions.
 *
 * The vendored grammars disagree, so the version policy is explicit here
 * rather than implied by a regex:
 *
 * - **1.0.x** — `arazzo-runtime-expression-1.0.1.abnf` has no
 *   `embedded-expression` / `expression-string` rule. The specification is
 *   prose only: "Expressions can be embedded into string values by
 *   surrounding the expression with `{}` curly braces." Its `json-pointer`
 *   `unescaped` range still includes `}` (0x7D), but the prose defines no
 *   delimiter rule for that case. We close at the first `}`; a JSON Pointer
 *   containing `}` must therefore be used as a bare expression, not embedded.
 *   The `${...}` spelling is kept as a legacy 1.0.x compatibility extension.
 * - **1.1.x / 1.2.x** — `arazzo-runtime-expression-1.1.0.abnf` defines
 *   `embedded-expression = "{" expression "}"`, and its JSON Pointer
 *   productions exclude `{`/`}` "for unambiguous embedded expression
 *   parsing". The first `}` always closes, and `${...}` is not an embedded
 *   expression, so it stays literal text. No 1.2 ABNF is vendored yet, so
 *   1.2.x follows the 1.1.x rule set.
 *
 * @internal stays out of the advertised contract; used by the ExpressionEngine facade.
 */
final class EmbeddedExpressionSplitter
{
    public function __construct(
        private readonly Parser $parser = new Parser(),
    ) {}

    /** @return list<EmbeddedSegment> */
    public function split(string $template, SpecVersion $version = SpecVersion::V1_0): array
    {
        $segments = [];
        $length = strlen($template);
        $literalStart = 0;
        $cursor = 0;

        while ($cursor < $length) {
            $token = $this->matchAt($template, $cursor, $version);

            if ($token === null) {
                $cursor++;

                continue;
            }

            if ($token['openOffset'] > $literalStart) {
                $segments[] = new EmbeddedSegment(
                    false,
                    substr($template, $literalStart, $token['openOffset'] - $literalStart),
                    $literalStart,
                );
            }

            $this->validate($template, $token['expression'], $token['openOffset']);

            $segments[] = new EmbeddedSegment(true, $token['expression'], $token['openOffset']);

            $literalStart = $token['end'];
            $cursor = $token['end'];
        }

        if ($literalStart < $length) {
            $segments[] = new EmbeddedSegment(false, substr($template, $literalStart), $literalStart);
        }

        return $segments;
    }

    /**
     * @return array{openOffset: int, end: int, expression: string}|null
     */
    private function matchAt(string $template, int $cursor, SpecVersion $version): ?array
    {
        $canonical = $this->canonicalAt($template, $cursor);

        if ($canonical !== null) {
            return $canonical;
        }

        return $this->allowsLegacyDollarBrace($version)
            ? $this->legacyDollarBraceAt($template, $cursor)
            : null;
    }

    /**
     * Canonical form shared by every spec version: `{$expression}`.
     *
     * @return array{openOffset: int, end: int, expression: string}|null
     */
    private function canonicalAt(string $template, int $cursor): ?array
    {
        if (($template[$cursor] ?? '') !== '{' || ($template[$cursor + 1] ?? '') !== '$') {
            return null;
        }

        $close = strpos($template, '}', $cursor + 2);

        if ($close === false) {
            $this->failUnterminated($template, $cursor);
        }

        return [
            'openOffset' => $cursor,
            'end' => $close + 1,
            'expression' => substr($template, $cursor + 1, $close - $cursor - 1),
        ];
    }

    /**
     * Legacy 1.0.x spelling: `${expression}`.
     *
     * `${$expression}` is deliberately not treated as legacy: the `$` stays
     * literal and the `{$expression}` token starts at the brace.
     *
     * @return array{openOffset: int, end: int, expression: string}|null
     */
    private function legacyDollarBraceAt(string $template, int $cursor): ?array
    {
        if (($template[$cursor] ?? '') !== '$' || ($template[$cursor + 1] ?? '') !== '{') {
            return null;
        }

        if (($template[$cursor + 2] ?? '') === '$') {
            return null;
        }

        $close = strpos($template, '}', $cursor + 2);

        if ($close === false) {
            $this->failUnterminated($template, $cursor);
        }

        return [
            'openOffset' => $cursor,
            'end' => $close + 1,
            'expression' => '$'.substr($template, $cursor + 2, $close - $cursor - 2),
        ];
    }

    private function allowsLegacyDollarBrace(SpecVersion $version): bool
    {
        return match ($version) {
            SpecVersion::V1_0 => true,
            SpecVersion::V1_1, SpecVersion::V1_2 => false,
        };
    }

    private function validate(string $template, string $expression, int $openingOffset): void
    {
        try {
            $this->parser->parse($expression);
        } catch (ExpressionSyntaxException $exception) {
            throw new ExpressionSyntaxException(
                $exception->getMessage(),
                $template,
                $openingOffset,
                $exception->path,
                $exception->codeId,
                $exception,
            );
        }
    }

    private function failUnterminated(string $template, int $openingOffset): never
    {
        throw new ExpressionSyntaxException(
            "Unterminated embedded expression in: {$template}",
            $template,
            $openingOffset,
            '',
            'expr.syntax',
        );
    }
}
