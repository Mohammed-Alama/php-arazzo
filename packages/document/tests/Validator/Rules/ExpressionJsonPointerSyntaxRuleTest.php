<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;
use Alama\Arazzo\Tests\Support\Fx;

it('flags bad pointer segment in request part; accepts valid response pointer', function (): void {
    $bodyOk = new RequestBody(null, new Expression('{$steps.a.request.body#/x}'), []);
    $s = Fx::step('a', 'op', body: $bodyOk, outputs: [
        'ok' => new Expression('{$steps.a.response.body#/ok}'),
    ]);
    $bodyBad = new RequestBody(null, new Expression('{$steps.a.request.body#/bad~9}'), []);
    $s2 = Fx::step('b', 'op', body: $bodyBad);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionJsonPointerSyntaxRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('skips step output references (non request/response part)', function (): void {
    $s = Fx::step('a', 'op', outputs: [
        'ok' => new Expression('{$steps.a.outputs.x}'),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    // Stub engine returns a Step reference with part='outputs' so line 30 is hit.
    $ref = new ExpressionReference(kind: ReferenceKind::Step, target: 'a', part: 'outputs');
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
    (new ExpressionJsonPointerSyntaxRule($engine))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
