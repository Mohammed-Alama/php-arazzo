<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\SourceDocument;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Alama\Arazzo\Runner\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Runner\Resolver\ResolvedOperation;
use cebe\openapi\Reader;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\NullLogger;

it('builds and sends an openapi request using the schema to route parameters', function () {
    $openapiJson = <<<'JSON'
    {
      "openapi": "3.0.0",
      "info": { "title": "API", "version": "1.0" },
      "servers": [{ "url": "https://api.example.com/v1" }],
      "paths": {
        "/users/{userId}": {
          "get": {
            "operationId": "getUser",
            "parameters": [
              { "name": "userId", "in": "path", "required": true, "schema": { "type": "integer" } },
              { "name": "limit", "in": "query", "schema": { "type": "integer" } },
              { "name": "X-Auth", "in": "header", "schema": { "type": "string" } }
            ]
          }
        }
      }
    }
    JSON;

    $openApi = Reader::readFromJson($openapiJson);

    $sourceDoc = new SourceDocument('test', SourceType::Openapi, 'http://test', json_decode($openapiJson, true));
    $sourceResolver = Mockery::mock(SourceResolver::class);
    $sourceResolver->shouldReceive('resolve')->andReturn($sourceDoc);

    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->withArgs(function ($request) {
        expect($request->getMethod())->toBe('GET');
        expect((string) $request->getUri())->toBe('https://api.example.com/v1/users/42?limit=10');
        expect($request->getHeaderLine('X-Auth'))->toBe('token123');

        return true;
    })->andReturn(new Response(200));

    $requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $requestFactory->shouldReceive('createRequest')->andReturnUsing(function ($method, $uri) {
        return new Request($method, $uri);
    });

    $executor = new DefaultOpenApiExecutor(
        $httpClient,
        $requestFactory,
        new NullLogger,
    );

    $payload = new OpenApiPayload(
        auto: ['userId' => '42', 'limit' => '10', 'X-Auth' => 'token123'],
    );

    $source = new SourceDescription('test', 'test.json', SourceType::Openapi);
    $normalized = new NormalizedOpenApiOperation(
        path: '/users/{userId}',
        method: 'get',
        resolvedServerUrl: 'https://api.example.com/v1',
        pathParameters: [
            'userId' => ['name' => 'userId', 'in' => 'path', 'schema' => ['type' => 'integer']],
        ],
        queryParameters: [
            'limit' => ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
        ],
        headerParameters: [
            'X-Auth' => ['name' => 'X-Auth', 'in' => 'header', 'schema' => ['type' => 'string']],
        ],
        cookieParameters: [],
        requestBodies: [],
        responses: [],
    );

    $resolved = new ResolvedOperation(
        source: $source,
        normalized: $normalized,
        openApi: $openApi,
        rawDocument: json_decode($openapiJson, true),
        cebeOperation: clone $openApi->paths->getPath('/users/{userId}')->get,
    );

    $response = $executor->execute($resolved, $payload);

    expect($response->getStatusCode())->toBe(200);
});
