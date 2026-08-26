<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Clients;

use Alama\Arazzo\Contracts\AiClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

class OpenAiClient implements AiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $apiKey,
        private string $endpoint,
        private string $model = 'gpt-4o',
        private float $temperature = 0.0,
    ) {}

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $this->temperature,
        ]);

        $stream = $this->streamFactory->createStream($payload ?: '');

        $request = $this->requestFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Authorization', 'Bearer '.$this->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->httpClient->sendRequest($request);

        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            $data = json_decode($responseBody, true);
            $errorMessage = $data['error']['message'] ?? $response->getReasonPhrase();
            throw new RuntimeException('API Error: '.$errorMessage);
        }

        $data = json_decode($responseBody, true);

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
