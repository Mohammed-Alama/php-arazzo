<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ActionOutputTargetExclusivityRule;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;
use Alama\Arazzo\Tests\Support\Fx;

/**
 * Stub engine that lets ActionOutputTargetExclusivityRule tests control
 * parseExpression/expressionReferences outcomes independently of the real
 * engine (which never yields null targets after a clean parse).
 */
final class StubExpressionEngine implements ExpressionEngineInterface
{
    public function __construct(
        private readonly ?ExpressionSyntaxException $syntaxError = null,
        private readonly ?ExpressionReference $reference = null,
    ) {}

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        return $this->syntaxError;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        return $this->reference;
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
}

it('accepts valid step output reference', function (): void {
    $step = Fx::step('fetch', 'op', outputs: ['result' => new Expression('{$steps.fetch.outputs.result}')]);
    $workflow = Fx::wf('w', [$step], outputs: ['out' => new Expression('{$steps.fetch.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts valid input reference', function (): void {
    $workflow = Fx::wf(
        'w',
        [],
        inputs: ['type' => 'object', 'properties' => ['username' => ['type' => 'string']]],
        outputs: ['out' => new Expression('{$inputs.username}')],
    );
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('rejects malformed expression', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent step', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.missing.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent workflow', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$workflows.ghost.outputs.foo}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent input', function (): void {
    $workflow = Fx::wf('w', [], inputs: [], outputs: ['out' => new Expression('{$inputs.missing}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('accepts null and scalar outputs', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out1' => null, 'out2' => 'literal', 'out3' => 42]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts workflow output reference to itself', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$workflows.w.outputs.foo}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('continues when workflow has no symbol table entry', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.x.outputs.y}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $symbols = new SymbolTable([], [], [], []);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, $symbols, $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('continues when expression parses cleanly but yields no reference', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.x.outputs.y}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $engine = new StubExpressionEngine(syntaxError: null, reference: null);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts step reference to the current step (null stepId)', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Step, target: null);
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts workflow reference with null workflowId', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$workflows.outputs.foo}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Workflow, target: null);
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts input reference with null inputName', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$inputs}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Input, target: null);
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts output reference with null declared output name', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$outputs}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Output, target: null);
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('rejects output reference to undeclared output', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$outputs.missing}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Output, target: 'missing');
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $symbols = SymbolTable::build($doc);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, $symbols, $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('accepts output reference to declared output', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['declared' => 'x', 'out' => new Expression('{$outputs.declared}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Output, target: 'declared');
    $engine = new StubExpressionEngine(syntaxError: null, reference: $ref);
    $symbols = SymbolTable::build($doc);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule($engine))->check($doc, $symbols, $ec);
    expect($ec->errors())->toHaveCount(0);
});
