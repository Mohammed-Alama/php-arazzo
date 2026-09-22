<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

it('defaults every data field', function (): void {
    $io = new StepIo();

    expect($io->parameters)->toBe([])
        ->and($io->requestBody)->toBeNull()
        ->and($io->successCriteria)->toBe([])
        ->and($io->outputs)->toBe([]);
});

it('carries parameters, body, criteria and outputs', function (): void {
    $body = new RequestBody('application/json', ['ok' => true], []);
    $io = new StepIo(
        parameters: [new Parameter('limit', ParameterIn::Query, 25)],
        requestBody: $body,
        successCriteria: [new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple)],
        outputs: ['id' => new Expression('{$response.body#/id}')],
    );

    expect($io->parameters[0]->name)->toBe('limit')
        ->and($io->requestBody)->toBe($body)
        ->and($io->successCriteria[0]->condition)->toBe('$statusCode == 200')
        ->and($io->outputs['id'])->toBeInstanceOf(Expression::class);
});
