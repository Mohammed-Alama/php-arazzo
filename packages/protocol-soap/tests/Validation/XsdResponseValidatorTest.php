<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Validation;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use Alama\Arazzo\Protocol\Soap\Validation\XsdResponseValidator;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use DOMDocument;

const XSD_ORDERS_WSDL = __DIR__.'/../../../sources/tests/fixtures/order-service.wsdl';

function xsdValidator(): XsdResponseValidator
{
    return new XsdResponseValidator(
        new WsdlOperationResolver(fetcher: new LocalFetcher(), normalizer: new WsdlSourceNormalizer()),
    );
}

function xsdValidatorStep(string $operationName, array $sources): array
{
    $document = new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        $sources,
        [],
        new Components([], [], [], []),
        [],
    );

    return [$document, new Step('get', null, new StepTarget(operationName: $operationName), new StepFlow(), new StepIo())];
}

function xsdResponseEnvelope(string $bodyXml): DOMDocument
{
    $dom = new DOMDocument();
    $dom->loadXML(
        '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body>'.$bodyXml.'</soapenv:Body></soapenv:Envelope>',
    );

    return $dom;
}

it('passes a response that conforms to the inline XSD schema', function (): void {
    $validator = xsdValidator();
    [$document, $step] = xsdValidatorStep(
        '$sourceDescriptions.orders.GetOrder',
        [new SourceDescription('orders', XSD_ORDERS_WSDL, SourceType::Wsdl)],
    );

    expect(fn () => $validator->validateResponseSchema(
        $step,
        200,
        'text/xml; charset=utf-8',
        xsdResponseEnvelope('<tns:GetOrderResponse xmlns:tns="urn:orders"><status>paid</status><total>12.50</total></tns:GetOrderResponse>'),
        $document,
    ))->not->toThrow(SchemaValidationException::class);
});

it('throws when the response is missing a required element', function (): void {
    $validator = xsdValidator();
    [$document, $step] = xsdValidatorStep(
        '$sourceDescriptions.orders.GetOrder',
        [new SourceDescription('orders', XSD_ORDERS_WSDL, SourceType::Wsdl)],
    );

    expect(fn () => $validator->validateResponseSchema(
        $step,
        200,
        'text/xml; charset=utf-8',
        xsdResponseEnvelope('<tns:GetOrderResponse xmlns:tns="urn:orders"><status>paid</status></tns:GetOrderResponse>'),
        $document,
    ))->toThrow(SchemaValidationException::class, "for step 'get'");
});

it('throws with the libxml details when an element has the wrong type', function (): void {
    $validator = xsdValidator();
    [$document, $step] = xsdValidatorStep(
        '$sourceDescriptions.orders.GetOrder',
        [new SourceDescription('orders', XSD_ORDERS_WSDL, SourceType::Wsdl)],
    );

    $caught = null;
    try {
        $validator->validateResponseSchema(
            $step,
            200,
            'text/xml; charset=utf-8',
            xsdResponseEnvelope('<tns:GetOrderResponse xmlns:tns="urn:orders"><status>paid</status><total>not-a-number</total></tns:GetOrderResponse>'),
            $document,
        );
    } catch (SchemaValidationException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->violations)->not->toBeEmpty();
    expect($caught->violations[0]['path'])->toContain('line ');
    expect(implode(' ', array_column($caught->violations, 'message')))->toContain('not-a-number');
});

it('passes through when the step does not reference a WSDL source', function (): void {
    $validator = xsdValidator();
    [$document, $step] = xsdValidatorStep(
        '$sourceDescriptions.rest.GetOrder',
        [new SourceDescription('rest', 'https://api.example.test/openapi.json', SourceType::Openapi)],
    );

    expect(fn () => $validator->validateResponseSchema(
        $step,
        200,
        'application/json',
        xsdResponseEnvelope('<nonsense/>'),
        $document,
    ))->not->toThrow(SchemaValidationException::class);
});

it('passes through when the decoded body is not a DOM document', function (): void {
    $validator = xsdValidator();
    [$document, $step] = xsdValidatorStep(
        '$sourceDescriptions.orders.GetOrder',
        [new SourceDescription('orders', XSD_ORDERS_WSDL, SourceType::Wsdl)],
    );

    expect(fn () => $validator->validateResponseSchema(
        $step,
        200,
        'text/xml; charset=utf-8',
        ['parsed' => true],
        $document,
    ))->not->toThrow(SchemaValidationException::class);
});
