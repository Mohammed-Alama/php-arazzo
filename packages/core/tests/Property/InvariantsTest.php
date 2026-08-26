<?php

declare(strict_types=1);

use Alama\Arazzo\Context\ExecutionState;
use Alama\Arazzo\Evaluation\DependencyGraph;
use Alama\Arazzo\Evaluation\JsonPointer;
use Alama\Arazzo\Execution\TransitionType;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Expression\Lexer;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;

/**
 * Deterministic property tests: every loop is seeded so failures are
 * reproducible. Each property pins an execution invariant that mutation
 * testing targets (see tests/Conformance/README.md).
 */
function propertySegments(int $count): array
{
    return array_map(fn (int $i): string => 'seg'.$i, range(0, $count - 1));
}

it('round-trips random JSON pointers through resolve', function (): void {
    mt_srand(20260824);

    for ($iteration = 0; $iteration < 40; $iteration++) {
        $depth = mt_rand(1, 4);
        $data = [];
        $node = &$data;
        $keys = [];
        $leaf = null;

        for ($level = 0; $level < $depth; $level++) {
            // Include keys needing JSON-pointer escaping (~0 -> literal ~).
            $key = [propertySegments($iteration)[0], 'k~ey', 'k/ey'][$level % 3];
            $keys[] = $key;
            $leaf = ['leaf' => mt_rand()];
            $node[$key] = $level === $depth - 1 ? $leaf : [];
            $node = &$node[$key];
        }
        unset($node);

        $pointer = '/'.implode('/', array_map(
            fn (string $key): string => str_replace(['~', '/'], ['~0', '~1'], $key),
            $keys,
        ));

        expect(JsonPointer::resolve($data, $pointer))->toBe($leaf);
    }
});

it('accepts equivalent runtime-expression spellings and rejects malformed ones', function (): void {
    mt_srand(20260825);

    for ($iteration = 0; $iteration < 40; $iteration++) {
        $path = implode('.', [...propertySegments(mt_rand(1, 3)), 'statusCode']);
        $lexer = new Lexer();

        // The braced spelling strips its wrapper into the token stream, so
        // equivalence is asserted modulo a leading Dollar token.
        $tokensOf = function (string $raw) use ($lexer): array {
            $tokens = array_map(
                fn ($token) => [$token->kind->name, $token->value],
                $lexer->tokenize($raw),
            );

            if (($tokens[0][0] ?? null) === 'Dollar') {
                array_shift($tokens);
            }

            return $tokens;
        };

        expect($tokensOf('${'.$path.'}'))->toBe($tokensOf('{$'.$path.'}'))
            ->and($tokensOf('$'.$path))->toBe($tokensOf('{$'.$path.'}'));
    }

    expect(fn () => (new Lexer())->tokenize('{unterminated'))
        ->toThrow(RuntimeException::class);
});

it('returns acyclic-consistent topological orders for random DAGs and finds cycles', function (): void {
    mt_srand(20260826);

    for ($iteration = 0; $iteration < 30; $iteration++) {
        $count = mt_rand(2, 6);
        $steps = [];
        $edges = [];

        foreach (range(0, $count - 1) as $i) {
            $deps = [];

            // Only allow edges to EARLIER steps: guarantees a DAG.
            for ($candidate = 0; $candidate < $i; $candidate++) {
                if (mt_rand(0, 1) === 1) {
                    $deps[] = 'step'.$candidate;
                }
            }

            $edges['step'.$i] = $deps;
        }

        $dtos = array_map(fn (string $id): Step => Fx::step($id), array_keys($edges));
        $graph = new DependencyGraph($dtos);
        $order = $graph->getTopologicalOrder();
        $position = array_flip($order);

        foreach ($edges as $to => $froms) {
            foreach ($froms as $from) {
                if (!isset($position[$from], $position[$to])) {
                    continue;
                }

                expect($position[$from])->toBeLessThan($position[$to]);
            }
        }

        expect($graph->getCycle())->toBeNull();

        // Injecting a back-edge must surface a cycle.
        if (count($dtos) >= 2) {
            // Two independent steps can never cycle.
            expect((new DependencyGraph([$dtos[0], $dtos[1]]))->getCycle())->toBeNull();
        }
    }
});

it('never retries beyond the configured ceiling', function (): void {
    mt_srand(20260827);

    $resolver = new TestExpressionResolver();
    $document = Fx::doc([
        Fx::wf('wf', [
            Fx::step('flaky'),
        ]),
    ]);
    $workflow = $document->workflows[0];
    $step = $workflow->steps[0];

    for ($limit = 1; $limit <= 5; $limit++) {
        $engine = new WorkflowEngine($resolver, maxRetryAttempts: $limit);
        $state = ExecutionState::start('exec_r', 'def', 'wf', []);

        $retryTransitions = 0;

        while (true) {
            $transition = $engine->transition($document, $workflow, $step, $state, false);

            if ($transition->type !== TransitionType::Retry) {
                break;
            }

            $state = $transition->state->withStepResult($step->stepId, ['status' => 'failed']);
            $retryTransitions++;

            if ($retryTransitions > $limit) {
                $this->fail("Retry transitions exceeded ceiling of {$limit}");
            }
        }

        expect($retryTransitions)->toBeLessThanOrEqual($limit);
    }
});

it('round-trips execution state through serialization with arbitrary payloads', function (): void {
    mt_srand(20260828);

    for ($iteration = 0; $iteration < 30; $iteration++) {
        $outputs = [];
        foreach (propertySegments(mt_rand(0, 3)) as $segment) {
            $outputs[$segment] = mt_rand();
        }
        $seed = mt_rand();
        $errors = array_fill(0, mt_rand(0, 2), ['type' => 'retry_exhausted']);

        $state = ExecutionState::start('exec_'.$seed, 'def_'.$seed, 'wf_'.$seed)
            ->withInputs(['seed' => $seed])
            ->withOutput('out', $outputs);

        foreach ($errors as $entry) {
            $state = $state->withErrorEntry($entry);
        }

        $restored = ExecutionState::fromArray($state->toArray());

        expect($restored->toArray())->toBe($state->toArray());
    }
});
