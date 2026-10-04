<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;

class StubResponseValidator implements ResponseValidatorInterface
{
    public int $called = 0;

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->called++;
    }
}

class FailingResponseValidator implements ResponseValidatorInterface
{
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        throw new SchemaValidationException('Schema mismatch', [], [], $document);
    }
}

function dispatcherStep(string $id = 's1'): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(), new StepIo());
}

it('dispatches to all registered validators', function (): void {
    $validator = new StubResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['ok' => true]);

    expect($validator->called)->toBe(1);
});

it('throws on first validation failure', function (): void {
    $validator = new FailingResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['bad']);
})->throws(SchemaValidationException::class);

it('does nothing with empty validator list', function (): void {
    $dispatcher = new ResponseValidatorDispatcher([]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', []);

    expect(true)->toBeTrue();
});
