<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Laravel\SoapServiceProvider;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Soap\Execution\SoapOperationExecutor;
use Alama\Arazzo\Protocol\Soap\Registrar\SoapRegistrar;
use Alama\Arazzo\Protocol\Soap\Resolver\XPathReplacementTargetResolver;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use Alama\Arazzo\Protocol\Soap\Validation\XsdResponseValidator;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\ResponseValidatorDispatcher;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

/**
 * F4.6: the SOAP vertical slice wires into the composed app exactly like the
 * HTTP slice — value seams, the plugin tag vocabulary, and the shared D/E
 * registries (union of both protocols), plus the WSDL fixture and a
 * container-resolved end-to-end execute against a fixture transport.
 *
 * The provider is registered at runtime (not via the package's
 * `extra.laravel.providers`, so the HTTP-only test app keeps its 2/1 counts)
 * to prove it composes on top of ProtocolHttpServiceProvider.
 */
beforeEach(function (): void {
    $this->app->register(SoapServiceProvider::class);
});

function soapProviderWsdl(): string
{
    return __DIR__.'/../../sources/tests/fixtures/calculator.wsdl';
}

function soapProviderResponseEnvelope(): string
{
    return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body><AddResponse xmlns="urn:calculator"><result>8</result></AddResponse></soapenv:Body>'
        .'</soapenv:Envelope>';
}

function soapProviderDocument(): ArazzoDocument
{
    $step = new Step(
        stepId: 's1',
        description: null,
        target: new StepTarget(operationName: '$sourceDescriptions.calculator.Add'),
        flow: new StepFlow(),
        io: new StepIo(
            parameters: [new Parameter('a', null, 5), new Parameter('b', null, 3)],
            requestBody: new RequestBody(null, null, [
                new PayloadReplacement('/soapenv:Envelope/soapenv:Body/tns:Add/a', 42, 'xpath'),
            ]),
            successCriteria: [new SuccessCriterion(null, '$statusCode == 200', null)],
        ),
    );

    $workflow = new Workflow(
        workflowId: 'wf-1',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: [],
        parameters: [],
    );

    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Calc', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('calculator', soapProviderWsdl(), SourceType::Wsdl)],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function soapProviderClient(): HttpClientInterface
{
    return new class() implements HttpClientInterface
    {
        /** @var list<RequestInterface> */
        public array $requests = [];

        public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
        {
            $this->requests[] = $request;

            return new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], soapProviderResponseEnvelope());
        }
    };
}

it('binds the SOAP value seams to their protocol-soap implementations', function (): void {
    expect(app(SoapOperationExecutor::class))->toBeInstanceOf(SoapOperationExecutor::class)
        ->and(app(XsdResponseValidator::class))->toBeInstanceOf(XsdResponseValidator::class)
        ->and(app(WsdlSourceNormalizer::class))->toBeInstanceOf(WsdlSourceNormalizer::class)
        ->and(app(SoapRegistrar::class))->toBeInstanceOf(SoapRegistrar::class);
});

it('tags the SOAP services under the configured plugin vocabulary', function (): void {
    $parentTag = config('arazzo.plugins.tags.0');
    $soapTag = config('arazzo.plugins.soap_operation_executor');

    expect($parentTag)->toBe('arazzo.plugins.*')
        ->and($soapTag)->toBe('arazzo.plugins.soap-operation-executor');

    $parent = array_map(
        static fn (object $service): string => $service::class,
        iterator_to_array(app()->tagged($parentTag), false),
    );
    $executors = iterator_to_array(app()->tagged($soapTag), false);

    expect($parent)
        ->toContain(WsdlSourceNormalizer::class)
        ->toContain(SoapOperationExecutor::class)
        ->toContain(XsdResponseValidator::class)
        ->toContain(XPathReplacementTargetResolver::class)
        ->and($executors)->toHaveCount(1)
        ->and($executors[0])->toBeInstanceOf(SoapOperationExecutor::class);
});

it('coexists with protocol-http in the shared capability registries', function (): void {
    $plugins = app(OperationExecutorRegistry::class)->all();

    expect($plugins)->toHaveCount(3)
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof SoapOperationExecutor))->toBeTrue()
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof HttpStepExecutor))->toBeTrue();

    $normalizers = app(SourceNormalizerRegistryInterface::class);

    expect($normalizers->get(SourceType::Wsdl))->toBeInstanceOf(WsdlSourceNormalizer::class)
        ->and($normalizers->get(SourceType::Openapi))->toBeInstanceOf(OpenApiSourceNormalizer::class);

    $property = new ReflectionProperty(ResponseValidatorDispatcher::class, 'validators');
    $validators = $property->getValue(app(ResponseValidatorDispatcher::class));

    expect($validators)->toHaveCount(2)
        ->and(collect($validators)->contains(static fn (object $v): bool => $v instanceof XsdResponseValidator))->toBeTrue()
        ->and(collect($validators)->contains(static fn (object $v): bool => $v instanceof ResponseSchemaValidator))->toBeTrue();
});

it('accepts a WSDL-backed fixture document through the container preflight gate', function (): void {
    app(SourceRegistry::class)->register(new SourceDocument(
        'calculator',
        SourceType::Wsdl,
        soapProviderWsdl(),
        [],
    ));

    $valid = app(PreflightValidator::class)->validate(soapProviderDocument());

    expect($valid->isValid())->toBeTrue();
});

it('routes WSDL steps and executes a SOAP request end-to-end through the shared executor registry', function (): void {
    $client = soapProviderClient();
    app()->instance(HttpClientInterface::class, $client);

    $document = soapProviderDocument();
    $step = $document->workflows[0]->steps[0];

    // Protocol routing happens at the shared registry (what UnifiedStepCarrier
    // drives): the container-built union must resolve a WSDL step to the SOAP
    // executor, not the HTTP executor.
    $executor = app(OperationExecutorRegistry::class)->resolve($step, $document);

    expect($executor)->toBeInstanceOf(SoapOperationExecutor::class);

    $outcome = $executor->execute(
        $step,
        new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'),
        $document,
        'exec-1',
    );

    expect($outcome->statusCode)->toBe(200)
        ->and($client->requests)->toHaveCount(1)
        ->and($client->requests[0]->getMethod())->toBe('POST')
        ->and((string) $client->requests[0]->getUri())->toBe('http://localhost:8080/soap/calculator')
        ->and($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class)
        ->and($outcome->responseBody['transfer']->hasView('xml'))->toBeTrue();

    $body = (string) $client->requests[0]->getBody();

    // The step's xpath-typed payload replacement writes into the built envelope.
    expect($body)->toContain('<a>42</a>')
        ->and($body)->toContain('<b>3</b>')
        ->and($body)->toContain('soapenv:Envelope');
});
