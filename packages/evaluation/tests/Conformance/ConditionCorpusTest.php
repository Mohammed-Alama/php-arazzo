<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation\Conformance;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Expression\ExpressionEngine;

/**
 * Runs the language-agnostic simple-conditions corpus. The fixture is the
 * contract: it is also executable by other Arazzo implementations.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
$fixturePath = __DIR__.'/../fixtures/conformance/conditions/conditions.json';

/** @var list<array<string, mixed>> $entries */
$entries = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, array{0: array<string, mixed>}> $dataset */
$dataset = [];
foreach ($entries as $entry) {
    $dataset[$entry['id']] = [$entry];
}

it('evaluates the simple-conditions conformance corpus', function (array $entry): void {
    /** @var array<string, mixed> $contextData */
    $contextData = $entry['context'] ?? [];
    /** @var array<string, mixed> $inputs */
    $inputs = $contextData['inputs'] ?? [];
    /** @var array<string, mixed>|null $request */
    $request = $contextData['request'] ?? null;
    /** @var array<string, mixed>|null $response */
    $response = $contextData['response'] ?? null;
    $stepId = is_string($contextData['stepId'] ?? null) ? $contextData['stepId'] : 'step';

    $context = (new WorkflowContext('corpus'))->withInputs($inputs);
    if ($request !== null) {
        $context = $context->withStepRequest($stepId, $request);
    }
    if ($response !== null) {
        $context = $context->withStepResponse($stepId, $response);
    }

    $result = (new ConditionEvaluator(new ExpressionEngine()))
        ->evaluate($entry['condition'], $context, $stepId);

    expect($result)->toBe($entry['expected']);
})->with($dataset);
