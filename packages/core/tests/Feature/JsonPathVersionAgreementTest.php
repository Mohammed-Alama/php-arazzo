<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\Validator\Data\Error;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;

const JSONPATH_VERSION_RULE = 'selector.type_supported';

$engine = new ExpressionEngine();
$registry = $engine->selectorPlugins();
$supportedVersions = $registry->supportedVersions(ExpressionType::JsonPath);

/**
 * @return array{0: ArazzoDocument}
 */
function jsonPathVersionDocument(?string $version): array
{
    $step = StepFactory::http(
        's',
        null,
        new StepFlow(),
        new StepIo(outputs: ['id' => new Selector(null, '$.x', ExpressionType::JsonPath, $version)]),
        operationId: 'op',
    );
    $workflow = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return [
        new ArazzoDocument(
            arazzo: '1.1.0',
            info: new Info('T', null, null, '1'),
            sourceDescriptions: [],
            workflows: [$workflow],
            components: new Components([], [], [], []),
            specificationExtensions: [],
            specVersion: SpecVersion::V1_1,
        ),
    ];
}

function jsonPathVersionResult(ArazzoDocument $document): ValidationResult
{
    return (new Validator(RuleSet::default(new ExpressionEngine())))->validate($document);
}

/** @param list<Error> $errors */
function jsonPathVersionErrors(array $errors): array
{
    return array_values(array_filter($errors, static fn (Error $e): bool => $e->code === JSONPATH_VERSION_RULE));
}

function jsonPathContext(): WorkflowContext
{
    return (new WorkflowContext('w'))->withStepResponse('s', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['x' => 'found'],
    ]);
}

it('accepts and evaluates every jsonpath version the evaluator implements', function (string $version): void {
    [$document] = jsonPathVersionDocument($version);

    expect(jsonPathVersionErrors(jsonPathVersionResult($document)->errors))->toBe([])
        ->and((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSelector(
            $document->workflows[0]->steps[0]->io->outputs['id'],
            jsonPathContext(),
            's',
        ))->toBe('found');
})->with($supportedVersions);

it('rejects a version outside the specification vocabulary', function (): void {
    [$document] = jsonPathVersionDocument('draft-99');
    $errors = jsonPathVersionErrors(jsonPathVersionResult($document)->errors);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->message)->toContain('draft-99')
        ->and($errors[0]->message)->toContain('rfc9535');
});

it('accepts a versionless selector and evaluates it as the engine default', function (): void {
    [$document] = jsonPathVersionDocument(null);

    expect(jsonPathVersionErrors(jsonPathVersionResult($document)->errors))->toBe([])
        ->and((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSelector(
            $document->workflows[0]->steps[0]->io->outputs['id'],
            jsonPathContext(),
            's',
        ))->toBe('found');
});
