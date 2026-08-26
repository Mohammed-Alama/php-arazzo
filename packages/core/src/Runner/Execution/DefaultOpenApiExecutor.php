<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Evaluation\TypeCaster;
use Alama\Arazzo\Runner\Resolver\ResolvedOperation;
use Exception;
use GuzzleHttp\Psr7\Utils;
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

    public function execute(
        ResolvedOperation $resolvedOperation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        $openApi = $resolvedOperation->openApi;

        $baseUrl = '';
        if ($openApi->servers && count($openApi->servers) > 0) {
            $baseUrl = rtrim($openApi->servers[0]->url, '/');
        }

        $method = strtoupper($resolvedOperation->normalized->method);
        $urlPath = $resolvedOperation->normalized->path;

        foreach ($payload->auto as $name => $value) {
            if (isset($resolvedOperation->normalized->pathParameters[$name])) {
                $payload->path[$name] = $value;
            } elseif (isset($resolvedOperation->normalized->headerParameters[$name])) {
                $payload->header[$name] = $value;
            } elseif (isset($resolvedOperation->normalized->cookieParameters[$name])) {
                $payload->cookie[$name] = $value;
            } else {
                $payload->query[$name] = $value;
            }
        }

        $payload->path = $this->castParameters($resolvedOperation->normalized->pathParameters, $payload->path);
        $payload->query = $this->castParameters($resolvedOperation->normalized->queryParameters, $payload->query);
        $payload->header = $this->castParameters($resolvedOperation->normalized->headerParameters, $payload->header);
        $payload->cookie = $this->castParameters($resolvedOperation->normalized->cookieParameters, $payload->cookie);

        $serializedPath = ParameterSerializer::serialize('path', $resolvedOperation->normalized->pathParameters, $payload->path);
        foreach ($serializedPath as $name => $value) {
            $style = $resolvedOperation->normalized->pathParameters[$name]['style'] ?? 'simple';
            $replacement = $style === 'simple' ? urlencode($value) : $value;
            // matrix and label include the prefix in the serialized value,
            // so we replace the template
            $urlPath = str_replace('{'.$name.'}', $replacement, $urlPath);
        }

        $url = $baseUrl.$urlPath;

        $serializedQuery = ParameterSerializer::serialize('query', $resolvedOperation->normalized->queryParameters, $payload->query);
        $filteredQuery = array_filter($serializedQuery, fn ($val) => $val !== '');
        if (!empty($filteredQuery)) {
            $url .= '?'.implode('&', array_values($filteredQuery));
        }

        $request = $this->requestFactory->createRequest($method, $url);

        $serializedHeader = ParameterSerializer::serialize('header', $resolvedOperation->normalized->headerParameters, $payload->header);
        foreach ($serializedHeader as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }

        $serializedCookie = ParameterSerializer::serialize('cookie', $resolvedOperation->normalized->cookieParameters, $payload->cookie);
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
            $headers = [];

            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = $values === [] ? [''] : array_values(array_map(strval(...), $values));
            }

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
