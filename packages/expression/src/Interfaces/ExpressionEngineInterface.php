<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Expression\Data\EmbeddedSegment;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

/**
 * Entry-point seam for Arazzo expression parsing, analysis, and evaluation.
 */
interface ExpressionEngineInterface
{
    /**
     * Parse an expression string. Returns null if valid, or the syntax exception if invalid.
     */
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    /**
     * Statically inspect what an expression references. Returns null on syntax error.
     */
    public function expressionReferences(string $raw): ?ExpressionReference;

    /**
     * Extract all runtime expressions embedded in a template string.
     *
     * Returns an array of bare expression strings (e.g., '$inputs.clientId').
     *
     * @return list<string>
     */
    public function extract(string $template): array;

    /**
     * Split a template string into ordered literal/expression segments with byte offsets.
     *
     * Each expression segment is validated with the same parser used for bare expressions.
     * The spec version selects the embedded-expression policy: 1.0.x is
     * prose-only and also accepts the legacy `${...}` spelling, while 1.1.x /
     * 1.2.x follow `embedded-expression = "{" expression "}"` and treat
     * `${...}` as literal text.
     *
     * @return list<EmbeddedSegment>
     *
     * @throws ExpressionSyntaxException on unterminated or invalid embedded expression
     */
    public function parseEmbedded(string $raw, SpecVersion $version = SpecVersion::V1_0): array;

    /**
     * Interpolate a template string by replacing embedded expressions with resolved values.
     *
     * @param  callable(string): mixed  $resolver  Receives bare expression (e.g., '$inputs.foo'), returns resolved value
     * @param  array{stringify?: (callable(mixed): string)|null}  $options
     */
    public function interpolate(string $template, callable $resolver, array $options = []): string;

    /**
     * Interpolate a template string by replacing embedded expressions with resolved values.
     */
    public function interpolateWithOptions(string $template, callable $resolver, ?InterpolationOptions $options = null): string;

    /**
     * Test if a string is a valid runtime expression.
     */
    public function test(string $raw): bool;

    /**
     * Evaluate an Arazzo expression against a run context.
     */
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;

    /**
     * Evaluate a selector (JSONPath, JSON-pointer or XPath) against the workflow context.
     */
    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed;

    /**
     * Evaluate a raw JSONPath expression against data.
     *
     * @param  array<array-key, mixed>|object  $data
     */
    public function jsonPath(string $expression, array|object $data): mixed;

    /**
     * Resolve a JSON Pointer against an array-shaped document.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed;

    /**
     * The selector-plugin registry this engine uses (and shares with its
     * SelectorEvaluator). Returns the same instance on every call.
     */
    public function selectorPlugins(): SelectorPluginRegistryInterface;
}
