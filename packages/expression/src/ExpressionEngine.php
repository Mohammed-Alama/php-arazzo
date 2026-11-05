<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Expression\Data\EmbeddedSegment;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;

/**
 * Concrete expression facade: static parsing and reference projection, plus
 * runtime evaluation of expressions and selectors.
 *
 * PHPMD counts twelve public methods (TooManyPublicMethods). That is the
 * facade's intended surface — parse, extract, interpolate, test, evaluate,
 * selectors, JSONPath, JSON Pointer and XPath — and splitting it would make
 * every caller depend on several objects instead of one entry point. Kept as a
 * single facade, not waived for convenience (#260).
 */
final readonly class ExpressionEngine implements ExpressionEngineInterface
{
    private ExpressionDependencyProvider $provider;

    public function __construct(
        private readonly ?SelectorPluginRegistryInterface $selectorPlugins = null,
    ) {
        $this->provider = new ExpressionDependencyProvider($this->selectorPlugins);
    }

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        $result = $this->provider->getParser()->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? $result : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        $result = $this->provider->getParser()->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? null : $this->provider->getParser()->projectReferences($raw);
    }

    public function extract(string $template): array
    {
        return $this->getInterpolator()->extract($template);
    }

    public function parseEmbedded(string $raw): array
    {
        $segments = [];
        $pattern = '/\{(?:\$([^}]+)\})|\$\{([^}]+)\}/';

        preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $lastEnd = 0;

        foreach ($matches as $match) {
            $fullMatch = $match[0][0];
            $offset = (int) $match[0][1];

            // With PREG_OFFSET_CAPTURE, non-participating capture groups are
            // ['', -1] rather than null, so check both groups for content.
            $inner = $this->captureGroupContent($match, 1) ?? $this->captureGroupContent($match, 2);

            // Literal segment preceding this expression.
            if ($offset > $lastEnd) {
                $segments[] = new EmbeddedSegment(false, substr($raw, $lastEnd, $offset - $lastEnd), $lastEnd);
            }

            // Expression segment — validate with the same parser used for bare expressions.
            if ($inner !== null) {
                $bare = str_starts_with($inner, '$') ? $inner : '$'.$inner;
                $this->provider->getParser()->parse($bare);

                $segments[] = new EmbeddedSegment(true, $bare, $offset);
            }

            $lastEnd = $offset + strlen($fullMatch);
        }

        // Trailing literal text after the last expression.
        if ($lastEnd < strlen($raw)) {
            $segments[] = new EmbeddedSegment(false, substr($raw, $lastEnd), $lastEnd);
        }

        return $segments;
    }

    /**
     * @param  array{0: array{0: string, int}, 1?: array{0: string, int}, 2?: array{0: string, int}}  $match
     */
    private function captureGroupContent(array $match, int $group): ?string
    {
        /** @var array{0: string, int}|null $capture */
        $capture = $match[$group] ?? null;

        if (!is_array($capture)) {
            return null;
        }

        $content = $capture[0];

        return $content !== '' ? $content : null;
    }

    public function interpolate(string $template, callable $resolver, array $options = []): string
    {
        return $this->getInterpolator()->interpolate($template, $resolver, $options);
    }

    public function interpolateWithOptions(string $template, callable $resolver, ?InterpolationOptions $options = null): string
    {
        return $this->getInterpolator()->interpolateWithOptions($template, $resolver, $options);
    }

    public function test(string $raw): bool
    {
        return $this->getInterpolator()->test($raw);
    }

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $this->provider->getExpressionEvaluator()->evaluate($expression, $context);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->provider->getSelectorEvaluator()->evaluate($selector, $context, $stepId);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return $this->provider->getJsonPathEvaluator()->evaluate($expression, $data);
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return JsonPointer::resolve($data, $pointer);
    }

    public function selectorPlugins(): SelectorPluginRegistryInterface
    {
        return $this->selectorPlugins ?? $this->provider->getSelectorPluginRegistry();
    }

    private function getInterpolator(): Interpolator
    {
        return $this->provider->getInterpolator();
    }
}
