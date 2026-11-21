<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\MessageRefRequiresReceiveRule;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;
use Alama\Arazzo\Tests\Support\Fx;

function messageRuleDocument(
    SpecVersion $sv,
    string $action,
    array $criteria = [],
    array $outputs = [],
): ArazzoDocument {
    $step = new Step(
        'wait',
        null,
        new StepTarget(action: $action, channelPath: 'channels/x'),
        new StepFlow(),
        new StepIo(successCriteria: $criteria, outputs: $outputs),
    );

    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

function httpRuleDocument(SpecVersion $sv, array $outputs = []): ArazzoDocument
{
    $step = new Step(
        'fetch',
        null,
        new StepTarget(operationId: 'op'),
        new StepFlow(),
        new StepIo(successCriteria: [], outputs: $outputs),
    );

    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('allows $message in receive step successCriteria', function (): void {
    $errors = new ErrorCollector();
    $criterion = new SuccessCriterion(null, '$message.payload#/status == 200', CriterionType::Simple);
    $doc = messageRuleDocument(SpecVersion::V1_1, 'receive', [$criterion]);

    (new MessageRefRequiresReceiveRule(new ExpressionEngine()))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toBe([])
        ->and($errors->warnings())->toBe([]);
});

it('allows $message in receive step outputs', function (): void {
    $errors = new ErrorCollector();
    $doc = messageRuleDocument(
        SpecVersion::V1_1,
        'receive',
        [],
        ['orderId' => new Expression('{$message.payload#/orderId}')],
    );

    (new MessageRefRequiresReceiveRule(new ExpressionEngine()))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toBe([])
        ->and($errors->warnings())->toBe([]);
});

it('flags $message in an HTTP step', function (): void {
    $errors = new ErrorCollector();
    $doc = httpRuleDocument(
        SpecVersion::V1_1,
        ['orderId' => new Expression('{$message.payload#/orderId}')],
    );

    (new MessageRefRequiresReceiveRule(new ExpressionEngine()))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('expr.message_requires_receive');
});

it('flags $message in a send step', function (): void {
    $errors = new ErrorCollector();
    $doc = messageRuleDocument(
        SpecVersion::V1_1,
        'send',
        [],
        ['orderId' => new Expression('{$message.payload#/orderId}')],
    );

    (new MessageRefRequiresReceiveRule(new ExpressionEngine()))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('expr.message_requires_receive');
});

it('flags $message with no step context', function (): void {
    $errors = new ErrorCollector();
    $step = new Step(
        'other',
        null,
        new StepTarget(action: 'receive', channelPath: 'channels/x'),
        new StepFlow(),
        new StepIo(),
    );
    $doc = new ArazzoDocument(
        arazzo: SpecVersion::V1_1->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow(
            'w', null, null, null, [], [$step], [], [],
            ['m' => new Expression('{$message.payload#/orderId}')], [],
        )],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
    // Workflow-level output referencing $message has no current step context.

    (new MessageRefRequiresReceiveRule(new ExpressionEngine()))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toHaveCount(1);
});

it('skips non-message references', function (): void {
    $step = Fx::step('fetch', 'op', outputs: ['result' => new Expression('{$steps.fetch.outputs.result}')]);
    $workflow = Fx::wf('w', [$step], outputs: ['out' => new Expression('{$steps.fetch.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ref = new ExpressionReference(kind: ReferenceKind::Step, target: 'fetch');
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
    (new MessageRefRequiresReceiveRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('findStep returns null for null stepId', function (): void {
    $wf = new Workflow('w', null, null, null, [], [], [], [], ['out' => new Expression('{$message.payload#/x}')], []);
    $doc = Fx::doc(workflows: [$wf]);
    $ref = new ExpressionReference(kind: ReferenceKind::Message, target: null);
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
    (new MessageRefRequiresReceiveRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
