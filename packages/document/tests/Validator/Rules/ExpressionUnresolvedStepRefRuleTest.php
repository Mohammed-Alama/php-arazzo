<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedStepRefRule;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;
use Alama\Arazzo\Tests\Support\Fx;

it('flags OutputPart referencing missing step output', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$inputs.userId}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.ghost}')),
    ]);
    $doc = Fx::doc(workflows: [
        new Workflow('main', null, null,
            ['type' => 'object', 'properties' => ['userId' => []]],
            [], [$s1, $s2], [], [], [], []),
    ]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags reference to unknown stepId', function (): void {
    $s = Fx::step('a', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.ghost.outputs.y}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('allows backward output references without dependsOn as implicit dependencies', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.y}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBeEmpty()->and($ec->warnings())->toBeEmpty();
});

it('flags forward output references when the workflow uses no dependsOn', function (): void {
    $s1 = Fx::step('first', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}')),
    ]);
    $s2 = Fx::step('second', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->warnings())->toBeEmpty();
});

it('warns instead of failing on forward references when the workflow uses dependsOn elsewhere', function (): void {
    $s0 = Fx::step('entry', 'op');
    $s0 = StepFactory::http($s0->stepId, null, new StepFlow(dependsOn: ['other']), new StepIo(), operationId: $s0->target->operationId);
    $s1 = Fx::step('first', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}')),
    ]);
    $s2 = Fx::step('second', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s0, $s1, $s2], dep: ['other'])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBeEmpty()
        ->and($ec->warnings())->toHaveCount(1)
        ->and($ec->warnings()[0]->code)->toBe('expr.forward_step_ref');
});

it('still flags a declared-but-missing output on an implicit backward dependency', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.ghost}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('skips expression site with no workflow context (null workflow)', function (): void {
    $wf = new Workflow('main', null, null, null, [], [], [], [], ['out' => new Expression('{$steps.x.outputs.y}')], []);
    $doc = Fx::doc(workflows: [$wf]);
    // Symbol table with no workflows -> $site->workflow is null during walk.
    $symbols = new SymbolTable([], [], [], []);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule(new ExpressionEngine()))->check($doc, $symbols, $ec);
    expect($ec->errors())->toBe([]);
});

it('flags implicit current-step reference outside step context', function (): void {
    $wf = new Workflow('main', null, null, null, [], [], [], [], ['out' => new Expression('{$steps.x.outputs.y}')], []);
    $doc = Fx::doc(workflows: [$wf]);
    $ref = new ExpressionReference(kind: ReferenceKind::Step, target: null);
    $engine = new class($ref) implements ExpressionEngineInterface
    {
        public function __construct(private readonly ExpressionReference $ref) {}

        public function parseExpression(string $raw): ?ExpressionSyntaxException
        {
            return null;
        }

        public function expressionReferences(string $raw): ?ExpressionReference
        {
            return $this->ref;
        }

        public function extract(string $template): array
        {
            return [];
        }

        public function parseEmbedded(string $raw, SpecVersion $version = SpecVersion::V1_0): array
        {
            return [];
        }

        public function interpolate(string $template, callable $resolver, array $options = []): string
        {
            return $template;
        }

        public function interpolateWithOptions(string $template, callable $resolver, ?InterpolationOptions $options = null): string
        {
            return $template;
        }

        public function test(string $raw): bool
        {
            return true;
        }

        public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
        {
            return null;
        }

        public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
        {
            return null;
        }

        public function jsonPath(string $expression, array|object $data): mixed
        {
            return null;
        }

        public function jsonPointer(array $data, ?string $pointer): mixed
        {
            return null;
        }

        public function selectorPlugins(): SelectorPluginRegistryInterface
        {
            return new class() implements SelectorPluginRegistryInterface
            {
                public function supportedVersions(int $expressionType): array
                {
                    return [];
                }
            };
        }
    };
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('expr.unresolved_step_ref');
});

it('returns false from isForwardReference when current step id is null', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $wf = new Workflow('main', null, null, null, [], [$s1], [], [], ['out' => new Expression('{$steps.first.outputs.y}')], []);
    $doc = Fx::doc(workflows: [$wf]);
    // Workflow-level output referencing existing step; currentStepId is null.
    $ref = new ExpressionReference(kind: ReferenceKind::Step, target: 'first');
    $engine = new class($ref) implements ExpressionEngineInterface
    {
        public function __construct(private readonly ExpressionReference $ref) {}

        public function parseExpression(string $raw): ?ExpressionSyntaxException
        {
            return null;
        }

        public function expressionReferences(string $raw): ?ExpressionReference
        {
            return $this->ref;
        }

        public function extract(string $template): array
        {
            return [];
        }

        public function parseEmbedded(string $raw, SpecVersion $version = SpecVersion::V1_0): array
        {
            return [];
        }

        public function interpolate(string $template, callable $resolver, array $options = []): string
        {
            return $template;
        }

        public function interpolateWithOptions(string $template, callable $resolver, ?InterpolationOptions $options = null): string
        {
            return $template;
        }

        public function test(string $raw): bool
        {
            return true;
        }

        public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
        {
            return null;
        }

        public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
        {
            return null;
        }

        public function jsonPath(string $expression, array|object $data): mixed
        {
            return null;
        }

        public function jsonPointer(array $data, ?string $pointer): mixed
        {
            return null;
        }

        public function selectorPlugins(): SelectorPluginRegistryInterface
        {
            return new class() implements SelectorPluginRegistryInterface
            {
                public function supportedVersions(int $expressionType): array
                {
                    return [];
                }
            };
        }
    };
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
