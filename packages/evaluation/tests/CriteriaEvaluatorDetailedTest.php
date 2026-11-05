<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Evaluation\Data\CriterionResultCollection;
use Alama\Arazzo\Evaluation\Enum\CriterionOutcome;
use Alama\Arazzo\Evaluation\Enum\CriterionReason;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\Events\CriterionEvaluatedEvent;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Expression\ExpressionEngine;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @param  list<SuccessCriterion>  $criteria
 */
function detailedStep(array $criteria): Step
{
    return StepFactory::http('x', null, new StepFlow(), new StepIo(successCriteria: $criteria), operationId: 'op');
}

/**
 * @param  array<array-key, mixed>  $body
 */
function detailedContext(array $body = []): WorkflowContext
{
    return (new WorkflowContext('w'))->withStepResponse('x', [
        'statusCode' => 200,
        'headers' => [],
        'body' => $body ?: [
            'ok' => true,
            'text' => 'hello world',
            'xml' => '<root><id>1</id></root>',
        ],
    ]);
}

function detailedEvaluator(?EventDispatcherInterface $dispatcher = null): CriteriaEvaluator
{
    return new CriteriaEvaluator(new ExpressionEngine(), null, null, null, $dispatcher);
}

it('reports a passing simple criterion as passed/evaluated', function (): void {
    $criterion = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);

    $results = detailedEvaluator()->evaluateCriteria([$criterion], detailedStep([$criterion]), detailedContext());

    expect($results)->toBeInstanceOf(CriterionResultCollection::class)
        ->and($results->count())->toBe(1)
        ->and($results->results[0]->criterion)->toBe($criterion)
        ->and($results->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and($results->results[0]->reason)->toBe(CriterionReason::Evaluated)
        ->and($results->results[0]->message)->toBeNull();
});

it('reports a clean simple false as failed/evaluated', function (): void {
    $criterion = new SuccessCriterion(null, '$response.body.ok == false', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Failed)
        ->and($results->results[0]->reason)->toBe(CriterionReason::Evaluated)
        ->and(detailedEvaluator()->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('reports a malformed simple condition as error/syntax_error', function (): void {
    $criterion = new SuccessCriterion(null, '$response.body.id ==', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::SyntaxError)
        ->and($results->results[0]->message)->toBe('Unexpected end of condition.')
        ->and(detailedEvaluator()->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('reports regex pass and fail as evaluated', function (): void {
    $pass = new SuccessCriterion('$response.body#/text', 'hello', CriterionType::Regex);
    $fail = new SuccessCriterion('$response.body#/text', 'nope', CriterionType::Regex);
    $step = detailedStep([$pass]);
    $context = detailedContext();

    expect(detailedEvaluator()->evaluateCriteria([$pass], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and(detailedEvaluator()->evaluateCriteria([$fail], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Failed);
});

it('reports an unresolved regex context as context_unresolved', function (): void {
    $missing = new SuccessCriterion('$response.body.missing', 'hello', CriterionType::Regex);
    $without = new SuccessCriterion(null, 'hello', CriterionType::Regex);
    $step = detailedStep([$missing]);
    $context = detailedContext();

    $evaluator = detailedEvaluator();

    expect($evaluator->evaluateCriteria([$missing], $step, $context)->results[0]->reason)->toBe(CriterionReason::ContextUnresolved)
        ->and($evaluator->evaluateCriteria([$without], $step, $context)->results[0]->reason)->toBe(CriterionReason::ContextUnresolved)
        ->and($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('reports jsonpath pass and fail as evaluated on the built-in path', function (): void {
    $pass = new SuccessCriterion('$response.body', '$.ok', CriterionType::JsonPath);
    $fail = new SuccessCriterion('$response.body', '$.nope', CriterionType::JsonPath);
    $step = detailedStep([$pass]);
    $context = detailedContext();

    expect(detailedEvaluator()->evaluateCriteria([$pass], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and(detailedEvaluator()->evaluateCriteria([$fail], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Failed);
});

it('reports a malformed jsonpath as error/selector_error on the built-in path', function (): void {
    $criterion = new SuccessCriterion('$response.body', '$[?(', CriterionType::JsonPath);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::SelectorError)
        ->and($results->results[0]->message)->not->toBeNull();
});

it('reports an unresolved jsonpath context as context_unresolved', function (): void {
    $criterion = new SuccessCriterion('$response.body.missing', '$.ok', CriterionType::JsonPath);
    $step = detailedStep([$criterion]);
    $context = detailedContext(['ok' => true]);

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::ContextUnresolved);
});

it('reports xpath pass and fail as evaluated', function (): void {
    $pass = new SuccessCriterion('$response.body#/xml', '/root/id', CriterionType::XPath, 'xpath-10');
    $fail = new SuccessCriterion('$response.body#/xml', '/root/missing', CriterionType::XPath, 'xpath-10');
    $step = detailedStep([$pass]);
    $context = detailedContext();

    expect(detailedEvaluator()->evaluateCriteria([$pass], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and(detailedEvaluator()->evaluateCriteria([$fail], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Failed);
});

it('evaluates xpath-31 criterion as passed', function (): void {
    $criterion = new SuccessCriterion('$response.body#/xml', '/root/id', CriterionType::XPath, 'xpath-31');
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Passed);
});

it('reports an unsupported xpath version as error/selector_error', function (): void {
    $criterion = new SuccessCriterion('$response.body#/xml', '/root/id', CriterionType::XPath, 'xpath-20');
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::SelectorError)
        ->and($results->results[0]->message)->toContain('xpath-20');
});

it('reports a plugin pass and fail as evaluated', function (): void {
    $plugin = new class() implements CriterionEvaluatorPluginInterface
    {
        public bool $result = true;

        public function name(): string
        {
            return 'double';
        }

        public function priority(): int
        {
            return 100;
        }

        public function supports(CriterionType|SuccessCriterion $criterion): bool
        {
            return true;
        }

        public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool
        {
            return $this->result;
        }
    };

    $criterion = new SuccessCriterion(null, 'anything', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $expression = new ExpressionEngine();
    $registry = new CriterionEvaluatorRegistry($expression);
    $registry->register($plugin, 100);
    $evaluator = new CriteriaEvaluator($expression, null, $registry);

    expect($evaluator->evaluateCriteria([$criterion], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and($evaluator->evaluateCriteria([$criterion], $step, $context)->results[0]->reason)->toBe(CriterionReason::Evaluated);

    $plugin->result = false;

    expect($evaluator->evaluateCriteria([$criterion], $step, $context)->results[0]->outcome)->toBe(CriterionOutcome::Failed);
});

it('reports a plugin throw as error/plugin_error instead of propagating', function (): void {
    $plugin = new class() implements CriterionEvaluatorPluginInterface
    {
        public function name(): string
        {
            return 'boom';
        }

        public function priority(): int
        {
            return 100;
        }

        public function supports(CriterionType|SuccessCriterion $criterion): bool
        {
            return true;
        }

        public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool
        {
            throw new \RuntimeException('plugin exploded');
        }
    };

    $criterion = new SuccessCriterion(null, 'anything', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $expression = new ExpressionEngine();
    $registry = new CriterionEvaluatorRegistry($expression);
    $registry->register($plugin, 100);
    $evaluator = new CriteriaEvaluator($expression, null, $registry);

    $results = $evaluator->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::PluginError)
        ->and($results->results[0]->message)->toBe('plugin exploded')
        ->and($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('reports an unresolved plugin context as context_unresolved', function (): void {
    $plugin = new class() implements CriterionEvaluatorPluginInterface
    {
        public function name(): string
        {
            return 'double';
        }

        public function priority(): int
        {
            return 100;
        }

        public function supports(CriterionType|SuccessCriterion $criterion): bool
        {
            return true;
        }

        public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool
        {
            return true;
        }
    };

    $criterion = new SuccessCriterion('$response.body.missing', 'anything', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();

    $expression = new ExpressionEngine();
    $registry = new CriterionEvaluatorRegistry($expression);
    $registry->register($plugin, 100);

    $results = (new CriteriaEvaluator($expression, null, $registry))->evaluateCriteria([$criterion], $step, $context);

    expect($results->results[0]->outcome)->toBe(CriterionOutcome::Error)
        ->and($results->results[0]->reason)->toBe(CriterionReason::ContextUnresolved);
});

it('short-circuits at the first non-passed criterion', function (): void {
    $pass = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    $fail = new SuccessCriterion(null, '$response.body.ok == false', CriterionType::Simple);
    $step = detailedStep([$pass, $fail, $pass]);
    $context = detailedContext();

    $results = detailedEvaluator()->evaluateCriteria([$pass, $fail, $pass], $step, $context);

    expect($results->count())->toBe(2)
        ->and($results->results[1]->outcome)->toBe(CriterionOutcome::Failed);
});

it('returns an empty result list for no criteria and treats it as success', function (): void {
    $evaluator = detailedEvaluator();
    $step = detailedStep([]);
    $context = detailedContext();

    expect($evaluator->evaluateCriteria([], $step, $context))->toBeInstanceOf(CriterionResultCollection::class)
        ->and($evaluator->evaluateCriteria([], $step, $context)->count())->toBe(0)
        ->and($evaluator->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('emits one CriterionEvaluatedEvent per evaluated criterion, carrying result, stepId and documentId', function (): void {
    $dispatcher = new SimpleEventDispatcher();
    $seen = [];

    $dispatcher->subscribe(CriterionEvaluatedEvent::class, function (CriterionEvaluatedEvent $event) use (&$seen) {
        $seen[] = $event;
    });

    $criterion = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();
    $document = null; // no document supplied -> documentId is null

    detailedEvaluator($dispatcher)->evaluateCriteria([$criterion], $step, $context, $document);

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->result->outcome)->toBe(CriterionOutcome::Passed)
        ->and($seen[0]->stepId)->toBe('x')
        ->and($seen[0]->documentId)->toBeNull();
});

it('emits events only up to the first non-passed criterion', function (): void {
    $dispatcher = new SimpleEventDispatcher();
    $seen = [];

    $dispatcher->subscribe(CriterionEvaluatedEvent::class, function (CriterionEvaluatedEvent $event) use (&$seen) {
        $seen[] = $event;
    });

    $pass = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    $fail = new SuccessCriterion(null, '$response.body.ok == false', CriterionType::Simple);
    $step = detailedStep([$pass, $fail, $pass]);
    $context = detailedContext();

    detailedEvaluator($dispatcher)->evaluateCriteria([$pass, $fail, $pass], $step, $context);

    expect($seen)->toHaveCount(2)
        ->and($seen[1]->result->outcome)->toBe(CriterionOutcome::Failed);
});

it('exposes the detailed API through the evaluation engine', function (): void {
    $criterion = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    $step = detailedStep([$criterion]);
    $context = detailedContext();
    $engine = new EvaluationEngine(expression: new ExpressionEngine());

    $results = $engine->evaluateCriteria([$criterion], $step, $context);

    expect($results)->toBeInstanceOf(CriterionResultCollection::class)
        ->and($results->count())->toBe(1)
        ->and($results->results[0]->outcome)->toBe(CriterionOutcome::Passed)
        ->and($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
});
