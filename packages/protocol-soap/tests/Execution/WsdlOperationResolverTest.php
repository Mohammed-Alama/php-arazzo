<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use RuntimeException;

function makeWsdlOperationResolver(): WsdlOperationResolver
{
    return new WsdlOperationResolver(fetcher: new LocalFetcher(), normalizer: new WsdlSourceNormalizer());
}

function wsdlStepDocument(array $sources, ?string $operationId = null, ?string $operationName = null): array
{
    $document = new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        $sources,
        [],
        new Components([], [], [], []),
        [],
    );

    return [$document, new Step('op', null, new StepTarget(operationId: $operationId, operationName: $operationName), new StepFlow(), new StepIo())];
}

function wsdlSource(string $name, string $path, SourceType $type = SourceType::Wsdl): SourceDescription
{
    return new SourceDescription(name: $name, url: $path, type: $type);
}

it('resolves a dotted source reference to the indexed WSDL operation', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        '$sourceDescriptions.calculator.Add',
    );

    $resolved = $resolver->resolve($step, $document);

    expect($resolved)->toBeInstanceOf(ResolvedOperation::class);
    expect($resolved->operationName)->toBe('Add');
    expect($resolved->binding())->toBe('soap');
    expect($resolved->normalized->resolvedServerUrl)->toBe('http://localhost:8080/soap/calculator');
    expect($resolved->normalized->requestBodies['application/xml']['namespace'])->toBe('urn:calculator');
});

it('resolves a plain operation id against the single described WSDL source', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        'Add',
    );

    $resolved = $resolver->resolve($step, $document);

    expect($resolved->operationName)->toBe('Add');
});

it('supports steps that reference a WSDL source and rejects others', function (): void {
    $resolver = makeWsdlOperationResolver();

    [$wsdlDocument, $wsdlStep] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        '$sourceDescriptions.calculator.Add',
    );

    [$openApiDocument, $openApiStep] = wsdlStepDocument(
        [wsdlSource('rest', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl', SourceType::Openapi)],
        '$sourceDescriptions.rest.Add',
    );

    [$mixedDocument, $mixedStep] = wsdlStepDocument(
        [
            wsdlSource('calc', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl'),
            wsdlSource('orders', __DIR__.'/../../../sources/tests/fixtures/order-service.wsdl'),
        ],
        'Add',
    );

    expect($resolver->supportsStep($wsdlStep, $wsdlDocument))->toBeTrue();
    expect($resolver->supportsStep($openApiStep, $openApiDocument))->toBeFalse();
    expect($resolver->supportsStep($mixedStep, $mixedDocument))->toBeFalse();
});

it('rejects action steps even when a WSDL source is described', function (): void {
    $resolver = makeWsdlOperationResolver();
    $document = new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        [],
        new Components([], [], [], []),
        [],
    );
    $step = new Step('op', null, new StepTarget(action: 'send'), new StepFlow(), new StepIo());

    expect($resolver->supportsStep($step, $document))->toBeFalse();
});

it('throws when the referenced operation does not exist in the WSDL', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        '$sourceDescriptions.calculator.Divide',
    );

    expect(fn () => $resolver->resolve($step, $document))->toThrow(RuntimeException::class);
});

it('throws when the step does not reference a WSDL source', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('rest', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl', SourceType::Openapi)],
        '$sourceDescriptions.rest.Add',
    );

    expect(fn () => $resolver->resolve($step, $document))->toThrow(RuntimeException::class);
});

it('resolves a dotted source reference supplied through operationName (spec form)', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        operationName: '$sourceDescriptions.calculator.Add',
    );

    $resolved = $resolver->resolve($step, $document);

    expect($resolved->operationName)->toBe('Add');
    expect($resolver->supportsStep($step, $document))->toBeTrue();
});

it('resolves a plain operationName against the single described WSDL source', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        operationName: 'Add',
    );

    expect($resolver->resolve($step, $document)->operationName)->toBe('Add');
});

it('prefers operationName over operationId when both are present', function (): void {
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('calculator', __DIR__.'/../../../sources/tests/fixtures/calculator.wsdl')],
        operationId: '$sourceDescriptions.calculator.Divide',
        operationName: '$sourceDescriptions.calculator.Add',
    );

    expect($resolver->resolve($step, $document)->operationName)->toBe('Add');
});

it('resolves the OAI Arazzo spec #533 customer-service WSDL via operationName', function (): void {
    // Conformance against the WSDL vendored from OAI Arazzo Specification PR #533
    // (`tests/fixtures/valid/v1.2.0/customer-service.wsdl`).
    $resolver = makeWsdlOperationResolver();
    [$document, $step] = wsdlStepDocument(
        [wsdlSource('customerService', __DIR__.'/../../../core/tests/fixtures/valid/v1.2.0/customer-service.wsdl')],
        operationName: '$sourceDescriptions.customerService.ListCustomers',
    );

    $resolved = $resolver->resolve($step, $document);

    expect($resolved->operationName)->toBe('ListCustomers');
    expect($resolved->binding())->toBe('soap');
    expect($resolved->normalized->requestBodies['application/xml']['namespace'])->toBe('http://example.com/customer-service');
});
