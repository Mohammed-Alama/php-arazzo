<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Normalizer\ResolvedOperation;
use Alama\Arazzo\Spec\OpenApiPayload;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class DefaultOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function execute(
        ResolvedOperation $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        $openApi = $operation->openApi;

        $baseUrl = '';
        if ($openApi->servers && count($openApi->servers) > 0) {
            $baseUrl = rtrim($openApi->servers[0]->url, '/');
        }

        $method = strtoupper($operation->normalized->method);
        $urlPath = $operation->normalized->path;

        $path = $payload->path;
        $query = $payload->query;
        $header = $payload->header;
        $cookie = $payload->cookie;

        foreach ($payload->auto as $name => $value) {
            if (isset($operation->normalized->pathParameters[$name])) {
                $path[$name] = $value;
            } elseif (isset($operation->normalized->headerParameters[$name])) {
                $header[$name] = $value;
            } elseif (isset($operation->normalized->cookieParameters[$name])) {
                $cookie[$name] = $value;
            } else {
                $query[$name] = $value;
            }
        }

        $path = $this->castParameters($operation->normalized->pathParameters, $path);
        $query = $this->castParameters($operation->normalized->queryParameters, $query);
        $header = $this->castParameters($operation->normalized->headerParameters, $header);
        $cookie = $this->castParameters($operation->normalized->cookieParameters, $cookie);

        $serializedPath = ParameterSerializer::serialize('path', $operation->normalized->pathParameters, $path);
        foreach ($serializedPath as $name => $value) {
            $style = $operation->normalized->pathParameters[$name]['style'] ?? 'simple';
            $replacement = $style === 'simple' ? urlencode($value) : $value;
            // matrix and label include the prefix in the serialized value,
            // so we replace the template
            $urlPath = str_replace('{'.$name.'}', $replacement, $urlPath);
        }

        $url = $baseUrl.$urlPath;

        $serializedQuery = ParameterSerializer::serialize('query', $operation->normalized->queryParameters, $query);
        $filteredQuery = array_filter($serializedQuery, fn ($val) => $val !== '');
        if (!empty($filteredQuery)) {
            $url .= '?'.implode('&', array_values($filteredQuery));
        }

        $request = $this->requestFactory->createRequest($method, $url);

        $serializedHeader = ParameterSerializer::serialize('header', $operation->normalized->headerParameters, $header);
        foreach ($serializedHeader as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }

        $serializedCookie = ParameterSerializer::serialize('cookie', $operation->normalized->cookieParameters, $cookie);
        if (!empty($serializedCookie)) {
            $cookieString = implode('; ', array_values($serializedCookie));
            $request = $request->withHeader('Cookie', $cookieString);
        }

        if ($payload->body !== null) {
            $mediaType = $payload->bodyMediaType ?? 'application/json';
            $request = $request->withHeader('Content-Type', $mediaType);

            $bodyStream = $mediaType === 'application/json'
                ? json_encode($payload->body, JSON_THROW_ON_ERROR)
                : (is_scalar($payload->body) ? (string) $payload->body : http_build_query((array) $payload->body));
            $request = $request->withBody(Utils::streamFor($bodyStream));
        }

        if ($requestInterceptor !== null) {
            $intercepted = $requestInterceptor($request);
            $request = $intercepted instanceof RequestInterface ? $intercepted : $request;
        }

        // PSR-18 cannot express per-request timeouts; delegate to Guzzle when
        // available so declared step timeouts are actually enforced.
        if ($timeoutSeconds !== null && $this->httpClient instanceof \GuzzleHttp\ClientInterface) {
            // Guzzle's options stub requires non-empty header value lists.

            $headers = array_map(function ($values) {
                return $values === [] ? [''] : array_values(array_map(strval(...), $values));
            }, $request->getHeaders());

            return $this->httpClient->request(
                $request->getMethod(),
                (string) $request->getUri(),
                [
                    'headers' => $headers,
                    'body' => (string) $request->getBody(),
                    'timeout' => $timeoutSeconds,
                ],
            );
        }

        return $this->httpClient->sendRequest($request);
    }

    /**
     * @param  array<string, array<string, mixed>>  $normalizedParams
     * @param  array<string, mixed>  $payloadParams
     * @return array<string, mixed>
     */
    private function castParameters(array $normalizedParams, array $payloadParams): array
    {
        $result = [];
        foreach ($payloadParams as $name => $value) {
            /** @var array<string, mixed>|null $schema */
            $schema = $normalizedParams[$name]['schema'] ?? null;
            $result[$name] = $this->castToSchemaType($value, $schema);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    private function castToSchemaType(mixed $value, ?array $schema): mixed
    {
        if ($schema === null || !isset($schema['type'])) {
            return $value;
        }

        try {
            return match ($schema['type']) {
                'integer' => TypeCaster::asInteger($value),
                'number' => TypeCaster::asFloat($value),
                'string' => TypeCaster::asString($value),
                'boolean' => TypeCaster::asBoolean($value),
                'array' => TypeCaster::asArray($value),
                default => $value,
            };
        } catch (Exception) {
            return $value;
        }
    }
}
