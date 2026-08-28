<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;
use cebe\openapi\spec\OpenApi;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

it('executes a workflow end-to-end', function () {
    // 1. Mock PSR-7/18
    $requestFactory = new class() implements RequestFactoryInterface
    {
        public function createRequest(string $method, $uri): RequestInterface
        {
            return new class($method, (string) $uri) implements RequestInterface
            {
                private array $headers = [];

                public function __construct(public string $method, public string $uri) {}

                public function getProtocolVersion(): string
                {
                    return '1.1';
                }

                public function withProtocolVersion($version): RequestInterface
                {
                    return $this;
                }

                public function getHeaders(): array
                {
                    return $this->headers;
                }

                public function hasHeader($name): bool
                {
                    return isset($this->headers[$name]);
                }

                public function getHeader($name): array
                {
                    return $this->headers[$name] ?? [];
                }

                public function getHeaderLine($name): string
                {
                    return implode(', ', $this->getHeader($name));
                }

                public function withHeader($name, $value): RequestInterface
                {
                    $c = clone $this;
                    $c->headers[$name] = (array) $value;

                    return $c;
                }

                public function withAddedHeader($name, $value): RequestInterface
                {
                    return $this;
                }

                public function withoutHeader($name): RequestInterface
                {
                    return $this;
                }

                public function getBody(): StreamInterface
                {
                    return new class() implements StreamInterface
                    {
                        public function __toString(): string
                        {
                            return '';
                        }

                        public function close(): void {}

                        public function detach() {}

                        public function getSize(): ?int
                        {
                            return null;
                        }

                        public function tell(): int
                        {
                            return 0;
                        }

                        public function eof(): bool
                        {
                            return true;
                        }

                        public function isSeekable(): bool
                        {
                            return false;
                        }

                        public function seek($offset, $whence = \SEEK_SET): void {}

                        public function rewind(): void {}

                        public function isWritable(): bool
                        {
                            return false;
                        }

                        public function write($string): int
                        {
                            return 0;
                        }

                        public function isReadable(): bool
                        {
                            return true;
                        }

                        public function read($length): string
                        {
                            return '';
                        }

                        public function getContents(): string
                        {
                            return '';
                        }

                        public function getMetadata($key = null)
                        {
                            return null;
                        }
                    };
                }

                public function withBody(StreamInterface $body): RequestInterface
                {
                    return $this;
                }

                public function getRequestTarget(): string
                {
                    return '';
                }

                public function withRequestTarget($requestTarget): RequestInterface
                {
                    return $this;
                }

                public function getMethod(): string
                {
                    return $this->method;
                }

                public function withMethod($method): RequestInterface
                {
                    return $this;
                }

                public function getUri(): UriInterface
                {
                    return new class($this->uri) implements UriInterface
                    {
                        public function __construct(private string $uri) {}

                        public function getScheme(): string
                        {
                            return '';
                        }

                        public function getAuthority(): string
                        {
                            return '';
                        }

                        public function getUserInfo(): string
                        {
                            return '';
                        }

                        public function getHost(): string
                        {
                            return '';
                        }

                        public function getPort(): ?int
                        {
                            return null;
                        }

                        public function getPath(): string
                        {
                            return '';
                        }

                        public function getQuery(): string
                        {
                            $parts = explode('?', $this->uri, 2);

                            return isset($parts[1]) ? $parts[1] : '';
                        }

                        public function getFragment(): string
                        {
                            return '';
                        }

                        public function withScheme($scheme): UriInterface
                        {
                            return $this;
                        }

                        public function withUserInfo($user, $password = null): UriInterface
                        {
                            return $this;
                        }

                        public function withHost($host): UriInterface
                        {
                            return $this;
                        }

                        public function withPort($port): UriInterface
                        {
                            return $this;
                        }

                        public function withPath($path): UriInterface
                        {
                            return $this;
                        }

                        public function withQuery($query): UriInterface
                        {
                            return $this;
                        }

                        public function withFragment($fragment): UriInterface
                        {
                            return $this;
                        }

                        public function __toString(): string
                        {
                            return $this->uri;
                        }
                    };
                }

                public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
                {
                    return $this;
                }
            };
        }
    };

    $responseMock = new class() implements ResponseInterface
    {
        public function getStatusCode(): int
        {
            return 201;
        }

        public function withStatus($code, $reasonPhrase = ''): ResponseInterface
        {
            return $this;
        }

        public function getReasonPhrase(): string
        {
            return 'Created';
        }

        public function getProtocolVersion(): string
        {
            return '1.1';
        }

        public function withProtocolVersion($version): ResponseInterface
        {
            return $this;
        }

        public function getHeaders(): array
        {
            return [];
        }

        public function hasHeader($name): bool
        {
            return false;
        }

        public function getHeader($name): array
        {
            return [];
        }

        public function getHeaderLine($name): string
        {
            return '';
        }

        public function withHeader($name, $value): ResponseInterface
        {
            return $this;
        }

        public function withAddedHeader($name, $value): ResponseInterface
        {
            return $this;
        }

        public function withoutHeader($name): ResponseInterface
        {
            return $this;
        }

        public function getBody(): StreamInterface
        {
            return new class() implements StreamInterface
            {
                public function __toString(): string
                {
                    return '{"data": {"id": 99}}';
                }

                public function close(): void {}

                public function detach() {}

                public function getSize(): ?int
                {
                    return null;
                }

                public function tell(): int
                {
                    return 0;
                }

                public function eof(): bool
                {
                    return true;
                }

                public function isSeekable(): bool
                {
                    return false;
                }

                public function seek($offset, $whence = \SEEK_SET): void {}

                public function rewind(): void {}

                public function isWritable(): bool
                {
                    return false;
                }

                public function write($string): int
                {
                    return 0;
                }

                public function isReadable(): bool
                {
                    return true;
                }

                public function read($length): string
                {
                    return '';
                }

                public function getContents(): string
                {
                    return '{"data": {"id": 99}}';
                }

                public function getMetadata($key = null)
                {
                    return null;
                }
            };
        }

        public function withBody(StreamInterface $body): ResponseInterface
        {
            return $this;
        }
    };

    $httpClient = new class($responseMock) implements ClientInterface
    {
        public array $requests = [];

        public function __construct(private ResponseInterface $response) {}

        public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
        {
            $this->requests[] = $request;

            return $this->response;
        }
    };

    // 2. Setup Document and Workflow
    $step = new Step(
        stepId: 'create-ride',
        description: 'Creates a ride',
        operationId: 'createRide',
        operationPath: null,
        workflowId: null,
        parameters: [
            new Parameter('customerId', ParameterIn::Query, new Expression('{$inputs.customerId}')),
        ],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '$statusCode == 201', null),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [
            'rideId' => new Expression('{$steps.create-ride.response.body#/data/id}'),
        ],
    );

    $workflow = new Workflow(
        workflowId: 'test-flow',
        summary: 'Test',
        description: 'Test',
        inputs: null,
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: ['finalRideId' => new Expression('{$steps.create-ride.outputs.rideId}')],
        parameters: [],
    );

    // Create a dummy openapi file
    $openapiJson = '{"openapi":"3.0.0","servers":[{"url":"https://api.test"}],"paths":{"/rides":{"post":{"operationId":"createRide","responses":{"201":{"description":"Created"}}}}}}';
    $tmpFile = tempnam(sys_get_temp_dir(), 'openapi_').'.json';
    file_put_contents($tmpFile, $openapiJson);

    $doc = new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [
            new SourceDescription('test-api', $tmpFile, SourceType::Openapi),
        ],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $sourceResolver = new class() implements SourceResolver
    {
        public function resolve(SourceDescription $description, string $basePath): SourceDocument
        {
            $json = json_decode(file_get_contents($description->url), true);

            return new SourceDocument($description->name, $description->type, $description->url, $json);
        }
    };
    $evaluator = new ExpressionEvaluator();
    $openApiLoader = new OpenApiDocumentLoader($sourceResolver);
    $operationResolver = new OpenApiOperationResolver($openApiLoader, new OpenApiVersionDetector(), new OpenApi30Normalizer(), new OpenApi31Normalizer());
    $outputExtractor = new ArazzoOutputExtractor($operationResolver, $evaluator);
    $criteriaEvaluator = new ArazzoCriteriaEvaluator($evaluator);
    $schemaValidator = new ArazzoSchemaValidator($operationResolver);
    $resolver = new ArazzoExpressionResolver($evaluator, $outputExtractor, $criteriaEvaluator, $schemaValidator);

    $openApiExecutor = new DefaultOpenApiExecutor($httpClient, $requestFactory);
    $stepExecutor = new StepExecutor($openApiExecutor, $resolver, $operationResolver);

    $workflowExecutor = new WorkflowExecutor($stepExecutor, new WorkflowEngine(new TestExpressionResolver()));

    $result = $workflowExecutor->execute($workflow, $doc, ['customerId' => 12345]);

    expect($result->status)->toBe('succeeded');
    expect($result->stepResults['create-ride']->success)->toBeTrue();
    expect($result->stepResults['create-ride']->outputs['rideId'])->toBe(99);
    expect($httpClient->requests)->toHaveCount(1);

    /** @var RequestInterface $req */
    $req = $httpClient->requests[0];
    expect($req->getMethod())->toBe('POST');
    expect($req->uri)->toBe('https://api.test/rides?customerId=12345');
});
