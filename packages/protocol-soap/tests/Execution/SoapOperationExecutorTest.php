<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\OperationResolverInterface;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Protocol\Soap\Execution\SoapFaultMapper;
use Alama\Arazzo\Protocol\Soap\Execution\SoapOperationExecutor;
use Alama\Arazzo\Protocol\Soap\Execution\SoapRequestBuilder;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransferMapper;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class SoapExecutorMockEngine implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $expression->raw;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return null;
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return null;
    }

    public function supportedXPathVersions(): array
    {
        return [];
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $value;
    }

    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if (is_string($value)) {
            return $this->interpolate($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        return $value;
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return $body;
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return null;
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return null;
    }
}

class SoapExecutorFakeResolver implements OperationResolverInterface
{
    public function __construct(private ResolvedOperation $resolved) {}

    public function supportsStep(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        return $this->resolved;
    }
}

class SoapExecutorFakeClient implements HttpClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public bool $throwOnSend = false;

    public function __construct(private ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
    {
        if ($this->throwOnSend) {
            throw new SoapExecutorClientException('connection refused');
        }

        $this->lastRequest = $request;

        return $this->response;
    }
}

class SoapExecutorClientException extends RuntimeException implements ClientExceptionInterface {}

function soapExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [new SourceDescription('calculator', 'calculator.wsdl', SourceType::Wsdl)],
        [],
        new Components([], [], [], []),
        [],
    );
}

function soapExecutorResolvedOperation(string $soapVersion = '1.1'): ResolvedOperation
{
    return new ResolvedOperation(
        source: new SourceDescription('calculator', 'calculator.wsdl', SourceType::Wsdl),
        normalized: new NormalizedOpenApiOperation(
            path: 'Add',
            method: 'POST',
            resolvedServerUrl: 'http://localhost:8080/soap/calculator',
            pathParameters: [],
            queryParameters: [],
            headerParameters: [
                'Content-Type' => ['value' => 'text/xml; charset=utf-8'],
                'SOAPAction' => ['value' => 'urn:calculator:Add'],
            ],
            cookieParameters: [],
            requestBodies: ['application/xml' => [
                'xsdElement' => 'tns:Add',
                'schema' => null,
                'portType' => 'CalculatorSoap',
                'soapVersion' => $soapVersion,
                'namespace' => 'urn:calculator',
            ]],
            responses: ['application/xml' => ['xsdElement' => 'tns:AddResponse', 'schema' => null]],
        ),
        operationName: 'Add',
    );
}

function soapExecutor(SoapExecutorFakeClient $client, OperationResolverInterface $resolver): SoapOperationExecutor
{
    $psr17 = new HttpFactory();

    return new SoapOperationExecutor(
        operationResolver: $resolver,
        requestBuilder: new SoapRequestBuilder(
            requestFactory: $psr17,
            streamFactory: $psr17,
            evaluationEngine: new SoapExecutorMockEngine(),
        ),
        httpClient: $client,
        responseMapper: new SoapResponseTransferMapper(),
        faultMapper: new SoapFaultMapper(),
    );
}

function soapExecutorStep(array $parameters = [], string $operationName = '$sourceDescriptions.calculator.Add'): Step
{
    return new Step('add', null, new StepTarget(operationName: $operationName), new StepFlow(), new StepIo(parameters: $parameters));
}

function soapExecutorContext(): WorkflowContext
{
    return new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');
}

it('supports WSDL steps and rejects non-SOAP steps', function (): void {
    $executor = new SoapOperationExecutor(
        operationResolver: new WsdlOperationResolver(fetcher: new LocalFetcher(), normalizer: new WsdlSourceNormalizer()),
        requestBuilder: new SoapRequestBuilder(
            requestFactory: new HttpFactory(),
            streamFactory: new HttpFactory(),
            evaluationEngine: new SoapExecutorMockEngine(),
        ),
        httpClient: new SoapExecutorFakeClient(new Response(200)),
        responseMapper: new SoapResponseTransferMapper(),
        faultMapper: new SoapFaultMapper(),
    );

    $document = soapExecutorDocument();
    expect($executor->name())->toBe('soap');
    expect($executor->priority())->toBe(50);
    expect($executor->supports(soapExecutorStep(), $document))->toBeTrue();
    expect($executor->supports(new Step('a', null, new StepTarget(action: 'send'), new StepFlow(), new StepIo()), $document))->toBeFalse();
    expect($executor->supports(new Step('b', null, new StepTarget(operationName: '$sourceDescriptions.rest.Add'), new StepFlow(), new StepIo()), $document))->toBeFalse();
});

it('posts a SOAP 1.1 envelope and reports the mapped transfer on success', function (): void {
    $responseBody = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><AddResponse xmlns="urn:calculator">8</AddResponse></soapenv:Body></soapenv:Envelope>';
    $client = new SoapExecutorFakeClient(new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $responseBody));
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation()));

    $outcome = $executor->execute(soapExecutorStep([new Parameter('a', null, 5), new Parameter('b', null, 3)]), soapExecutorContext(), soapExecutorDocument(), 'exec-1');

    expect($client->lastRequest)->not->toBeNull();
    expect((string) $client->lastRequest->getUri())->toBe('http://localhost:8080/soap/calculator');
    expect($client->lastRequest->getMethod())->toBe('POST');
    expect($client->lastRequest->getHeaderLine('Content-Type'))->toBe('text/xml; charset=utf-8');
    expect($client->lastRequest->getHeaderLine('SOAPAction'))->toBe('urn:calculator:Add');

    $body = (string) $client->lastRequest->getBody();
    expect($body)->toContain('soapenv:Envelope');
    expect($body)->toContain('<tns:Add xmlns:tns="urn:calculator">');
    expect($body)->toContain('<a>5</a>');
    expect($body)->toContain('<b>3</b>');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(200);
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class);
    expect($outcome->failureCategory)->toBeNull();
});

it('overrides the soap action from a step parameter', function (): void {
    $responseBody = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><AddResponse xmlns="urn:calculator">8</AddResponse></soapenv:Body></soapenv:Envelope>';
    $client = new SoapExecutorFakeClient(new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $responseBody));
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation()));

    $executor->execute(
        soapExecutorStep([new Parameter('message', null, 'hi'), new Parameter('soapAction', null, 'urn:custom-actions:Add')]),
        soapExecutorContext(),
        soapExecutorDocument(),
        'exec-1',
    );

    expect($client->lastRequest->getHeaderLine('SOAPAction'))->toBe('urn:custom-actions:Add');

    $body = (string) $client->lastRequest->getBody();
    expect($body)->toContain('<message>hi</message>');
    expect($body)->not->toContain('<soapAction>');
});

it('uses the SOAP 1.2 content type and drops the SOAPAction header on 1.2 operations', function (): void {
    $responseBody = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"><soapenv:Body><AddResponse xmlns="urn:calculator">8</AddResponse></soapenv:Body></soapenv:Envelope>';
    $client = new SoapExecutorFakeClient(new Response(200, ['Content-Type' => 'application/soap+xml; charset=utf-8'], $responseBody));
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation('1.2')));

    $outcome = $executor->execute(soapExecutorStep([new Parameter('a', null, 5)]), soapExecutorContext(), soapExecutorDocument(), 'exec-1');

    expect($client->lastRequest->getHeaderLine('Content-Type'))->toBe('application/soap+xml; charset=utf-8; action="urn:calculator:Add"');
    expect($client->lastRequest->hasHeader('SOAPAction'))->toBeFalse();

    $body = (string) $client->lastRequest->getBody();
    expect($body)->toContain('xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"');

    expect($outcome->statusCode)->toBe(200);
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class);
});

it('maps a SOAP fault returned with HTTP 200 into a failed outcome', function (): void {
    $faultBody = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><soapenv:Fault><faultcode>soapenv:Server</faultcode><faultstring>integer overflow</faultstring></soapenv:Fault></soapenv:Body></soapenv:Envelope>';
    $client = new SoapExecutorFakeClient(new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $faultBody));
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation()));

    $outcome = $executor->execute(soapExecutorStep([new Parameter('a', null, 5), new Parameter('b', null, 3)]), soapExecutorContext(), soapExecutorDocument(), 'exec-1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->failureCategory)->toBe('soap-fault');
    expect($outcome->responseBody['soap.faultcode'])->toBe('soapenv:Server');
    expect($outcome->responseBody['soap.faultstring'])->toBe('integer overflow');
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class);
});

it('maps a non-2xx response into a transport failure', function (): void {
    $client = new SoapExecutorFakeClient(new Response(502, ['Content-Type' => 'text/plain'], 'bad gateway'));
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation()));

    $outcome = $executor->execute(soapExecutorStep(), soapExecutorContext(), soapExecutorDocument(), 'exec-1');

    expect($outcome->failureCategory)->toBe('transport');
    expect($outcome->statusCode)->toBe(502);
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class);
});

it('fails with the transport category when the client throws a transport exception', function (): void {
    $client = new SoapExecutorFakeClient(new Response(200));
    $client->throwOnSend = true;
    $executor = soapExecutor($client, new SoapExecutorFakeResolver(soapExecutorResolvedOperation()));

    $outcome = $executor->execute(soapExecutorStep(), soapExecutorContext(), soapExecutorDocument(), 'exec-1');

    expect($outcome->failureCategory)->toBe('transport');
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(SoapResponseTransfer::class);
    expect($outcome->responseBody['transfer']->meta()['error'])->toBe('connection refused');
});
