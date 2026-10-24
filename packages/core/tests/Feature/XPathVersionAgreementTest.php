<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\Validator\Data\Error;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;

const XPATH_VERSION_RULE = 'step.success_criteria_version_supported';

$supportedVersions = (new ExpressionEngine())->supportedXPathVersions();

/**
 * @return array{0: ArazzoDocument, 1: Step}
 */
function xpathVersionDocument(?string $version): array
{
    $step = StepFactory::http(
        'x',
        null,
        new StepFlow(),
        new StepIo(successCriteria: [
            new SuccessCriterion(null, '/root/id', CriterionType::XPath, $version),
        ]),
        operationId: 'op',
    );
    $workflow = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return [
        new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []),
        $step,
    ];
}

function xpathVersionResult(ArazzoDocument $document): ValidationResult
{
    return (new Validator(RuleSet::default(new ExpressionEngine())))->validate($document);
}

/** @param list<Error> $errors */
function xpathVersionErrors(array $errors): array
{
    return array_values(array_filter($errors, static fn (Error $e): bool => $e->code === XPATH_VERSION_RULE));
}

function xpathXmlContext(): WorkflowContext
{
    return (new WorkflowContext('w'))->withStepResponse('x', [
        'statusCode' => 200,
        'headers' => [],
        'body' => '<root><id>1</id></root>',
    ]);
}

it('accepts and evaluates every xpath version the evaluator implements', function (string $version): void {
    [$document, $step] = xpathVersionDocument($version);

    expect(xpathVersionErrors(xpathVersionResult($document)->errors))->toBe([])
        ->and((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSuccessCriteria($step, xpathXmlContext(), $document))
        ->toBeTrue();
})->with($supportedVersions);

it('rejects every spec-recognised xpath version the evaluator cannot implement', function (string $version): void {
    [$document, $step] = xpathVersionDocument($version);
    $errors = xpathVersionErrors(xpathVersionResult($document)->errors);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->message)->toContain($version)
        ->and($errors[0]->message)->toContain('xpath-10');

    // The refusal is honest: this is exactly the criterion the runtime would fail.
    expect((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSuccessCriteria($step, xpathXmlContext(), $document))
        ->toBeFalse();
})->with(['xpath-20', 'xpath-30', 'xpath-31']);

it('rejects a version outside the specification vocabulary', function (): void {
    [$document] = xpathVersionDocument('xpath-99');
    $errors = xpathVersionErrors(xpathVersionResult($document)->errors);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->message)->toContain('xpath-99')
        ->and($errors[0]->message)->toContain('xpath-10');
});

it('accepts a versionless criterion and evaluates it as xpath-10', function (): void {
    [$document, $step] = xpathVersionDocument(null);

    expect(xpathVersionErrors(xpathVersionResult($document)->errors))->toBe([])
        ->and((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSuccessCriteria($step, xpathXmlContext(), $document))
        ->toBeTrue();
});
