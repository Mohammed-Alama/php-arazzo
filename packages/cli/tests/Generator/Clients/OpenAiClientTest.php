<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator\Clients;

use Alama\Arazzo\Cli\Generator\Clients\OpenAiClient;
use Mockery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

it('sends prompt to openai and returns content', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $streamFactory = Mockery::mock(StreamFactoryInterface::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $requestStream = Mockery::mock(StreamInterface::class);
    $responseStream = Mockery::mock(StreamInterface::class);

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $apiKey = 'test-key';

    $payloadArray = [
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => 'system_instructions'],
            ['role' => 'user', 'content' => 'user_trace'],
        ],
        'temperature' => 0.0,
    ];
    $payloadJson = json_encode($payloadArray);

    $streamFactory->shouldReceive('createStream')
        ->with($payloadJson)
        ->andReturn($requestStream);

    $requestFactory->shouldReceive('createRequest')
        ->with('POST', $endpoint)
        ->andReturn($request);

    $request->shouldReceive('withHeader')
        ->with('Authorization', 'Bearer '.$apiKey)
        ->andReturn($request);

    $request->shouldReceive('withHeader')
        ->with('Content-Type', 'application/json')
        ->andReturn($request);

    $request->shouldReceive('withBody')
        ->with($requestStream)
        ->andReturn($request);

    $httpClient->shouldReceive('sendRequest')
        ->with($request)
        ->andReturn($response);

    $response->shouldReceive('getStatusCode')
        ->andReturn(200);

    $response->shouldReceive('getBody')
        ->andReturn($responseStream);

    $responseStream->shouldReceive('__toString')
        ->andReturn(json_encode([
            'choices' => [
                ['message' => ['content' => 'generated_yaml']],
            ],
        ]));

    $client = new OpenAiClient($httpClient, $requestFactory, $streamFactory, $apiKey, $endpoint, 'gpt-4o');
    $result = $client->generate('system_instructions', 'user_trace');

    expect($result)->toBe('generated_yaml');
});

it('throws exception when api returns error', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $streamFactory = Mockery::mock(StreamFactoryInterface::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $requestStream = Mockery::mock(StreamInterface::class);
    $responseStream = Mockery::mock(StreamInterface::class);

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $apiKey = 'test-key';

    $streamFactory->shouldReceive('createStream')->andReturn($requestStream);

    $requestFactory->shouldReceive('createRequest')->andReturn($request);

    $request->shouldReceive('withHeader')->andReturn($request);
    $request->shouldReceive('withBody')->andReturn($request);

    $httpClient->shouldReceive('sendRequest')->andReturn($response);

    $response->shouldReceive('getStatusCode')
        ->andReturn(401);

    $response->shouldReceive('getBody')
        ->andReturn($responseStream);

    $responseStream->shouldReceive('__toString')
        ->andReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key',
            ],
        ]));

    $client = new OpenAiClient($httpClient, $requestFactory, $streamFactory, $apiKey, $endpoint, 'gpt-4o');

    expect(fn () => $client->generate('system_instructions', 'user_trace'))
        ->toThrow(RuntimeException::class, 'API Error: Invalid API key');
});
