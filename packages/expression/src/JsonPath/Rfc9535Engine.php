<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

use Alama\Arazzo\Expression\Data\JsonPathBudget;
use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;

/**
 * Self-contained RFC 9535 JSONPath evaluator (facade).
 *
 * Orchestrates tokenization, parsing, and step-by-step selector evaluation
 * with query admission budgets so evaluation always terminates and never hangs.
 *
 * Result shape mirrors the classic engine it replaces: a single match
 * unwraps to the bare value; multiple matches stay an array.
 *
 * Step-kind evaluation is delegated to {@see SelectorSteps}; filter predicate
 * evaluation to {@see PredicateEvaluator}; function/path resolution to
 * {@see JsonPathFunctions}.
 *
 * @internal stays out of the advertised contract; consumed by JsonPathEvaluator.
 */
final class Rfc9535Engine implements SelectorContext
{
    /** @var list<string> */
    private const SUPPORTED_VERSIONS = ['rfc9535'];

    private SelectorSteps $steps;

    private PredicateEvaluator $predicateEvaluator;

    public function __construct(private JsonPathBudget $budget = new JsonPathBudget())
    {
        $this->steps = new SelectorSteps($this, $budget);
        $this->predicateEvaluator = new PredicateEvaluator($budget, new JsonPathFunctions());
    }

    /**
     * @param  array<array-key, mixed>|object  $data
     */
    public function find(string $expression, array|object $data): mixed
    {
        if (strlen($expression) > $this->budget->maxExpressionLength) {
            throw SelectorEvaluationException::jsonPathBudgetExceeded('expressionLength');
        }

        $tokens = (new Tokenizer())->tokenize($expression);
        $parser = new Parser($tokens);
        /** @var array{kind: string, steps: list<array<array-key, mixed>>}  $ast */
        $ast = $parser->parse();

        $visited = 0;
        /** @var list<array<array-key, mixed>>  $results */
        $results = $this->select($ast, $this->normalizeRoot($data), '$', $visited, 0);

        if (count($results) > $this->budget->maxResultSetSize) {
            throw SelectorEvaluationException::jsonPathBudgetExceeded('resultSetSize');
        }

        return $results;
    }

    /** @return list<string> */
    public function supportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    public function budget(): JsonPathBudget
    {
        return $this->budget;
    }

    /**
     * @param  array<array-key, mixed>|object  $data
     * @return list<array<array-key, mixed>>
     */
    private function normalizeRoot(array|object $data): array
    {
        if (is_object($data)) {
            return [(array) $data];
        }

        return [$data];
    }

    /**
     * {@inheritDoc}
     *
     * @param  array{kind: string, steps: list<array<array-key, mixed>>}  $ast
     * @param  list<array<array-key, mixed>>  $nodes
     * @param  int  $visited  node visits (passed by reference)
     * @param  int  $depth  current selector nesting depth
     * @return list<array<array-key, mixed>>
     */
    public function select(array $ast, array $nodes, string $path, int &$visited, int $depth = 0): array
    {
        $current = $nodes;

        foreach ($ast['steps'] as $rawStep) {
            $depth++;
            if ($depth > $this->budget->maxSelectorDepth) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('selectorDepth');
            }

            $next = [];

            foreach ($current as $node) {
                $this->budget->touchNode($visited);
                /** @var array<array-key, mixed>  $step */
                $step = $rawStep;
                $next = array_merge($next, $this->applyStep($step, $node, $path, $visited, $depth));
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * @param  array<array-key, mixed>  $step
     * @return list<array<array-key, mixed>>
     */
    private function applyStep(array $step, mixed $node, string $path, int &$visited, int $depth): array
    {
        $kind = $step['kind'];
        /** @var array{name?: string, child?: array<array-key, mixed>, predicate?: array<array-key, mixed>, parts?: list<array<array-key, mixed>>}  $step */

        return match ($kind) {
            'child' => $this->steps->child($node, $step['name'] ?? ''),
            'recursive-descent' => $this->steps->recursiveDescent(
                $node,
                /** @var array<array-key, mixed> */ ($step['child'] ?? []),
                $path,
                $visited,
                $depth,
            ),
            'wildcard' => $this->steps->wildcard($node, $visited),
            'slice' => $this->steps->slice($node, $step, $visited),
            'filter' => $this->predicateEvaluator->filter(
                /** @var array<array-key, mixed> */ ($step['predicate'] ?? []),
                $node,
                $visited,
            ),
            'union' => $this->applyUnion($step, $node, $path, $visited, $depth),
            default => [],
        };
    }

    /**
     * @param  array<array-key, mixed>  $step
     * @return list<array<array-key, mixed>>
     */
    private function applyUnion(array $step, mixed $node, string $path, int &$visited, int $depth): array
    {
        /** @var list<array<array-key, mixed>>  $parts */
        $parts = $step['parts'] ?? [];
        $out = [];

        /** @var list<array<array-key, mixed>>  $nodeArr */
        $nodeArr = [$node];

        foreach ($parts as $rawPart) {
            /** @var array<array-key, mixed>  $part */
            $part = $rawPart;
            $out = array_merge(
                $out,
                $this->select(
                    ['kind' => 'root', 'steps' => [$part]],
                    $nodeArr,
                    $path,
                    $visited,
                    $depth,
                ),
            );
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }
}
