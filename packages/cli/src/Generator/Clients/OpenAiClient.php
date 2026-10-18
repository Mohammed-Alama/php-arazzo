<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Generator\Clients;

use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
            $message = self::dig($data, 'error', 'message');

            // The reason phrase stands in for a missing or unstringifiable error payload.
            $detail = is_scalar($message) ? (string) $message : $response->getReasonPhrase();

            throw new RuntimeException('API Error: '.$detail);
        }

        $data = json_decode($responseBody, true);

        return self::text(self::dig($data, 'choices', 0, 'message', 'content'));
    }

    /**
     * Walks a decoded response along a key path. The API is untrusted, so every
     * level has to be established rather than assumed; the first level that is
     * missing or is not a map ends the walk with null.
     */
    private static function dig(mixed $payload, int|string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($payload)) {
                return null;
            }

            $payload = $payload[$key] ?? null;
        }

        return $payload;
    }

    /**
     * Narrows a textual response field; anything unstringifiable reads as empty.
     */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
