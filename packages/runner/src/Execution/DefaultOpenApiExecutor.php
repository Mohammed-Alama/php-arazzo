<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class DefaultOpenApiExecutor implements OpenApiExecutorInterface
{
    private readonly ParameterSerializer $parameterSerializer;

    private readonly TypeCaster $typeCaster;

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?LoggerInterface $logger = null,
    ) {
        $this->parameterSerializer = new ParameterSerializer();
        $this->typeCaster = new TypeCaster();
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function execute(
        OpenApiOperationHandle $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        $openApi = $operation->openApi;
        $normalized = $operation->operation->normalized;

        $baseUrl = '';
        if ($openApi->servers && count($openApi->servers) > 0) {
            $baseUrl = rtrim($openApi->servers[0]->url, '/');
        }

        $method = strtoupper($normalized->method);
        $urlPath = $normalized->path;

        $path = $payload->path;
        $query = $payload->query;
        $header = $payload->header;
        $cookie = $payload->cookie;

        foreach ($payload->auto as $name => $value) {
            if (isset($normalized->pathParameters[$name])) {
                $path[$name] = $value;
            } elseif (isset($normalized->headerParameters[$name])) {
                $header[$name] = $value;
            } elseif (isset($normalized->cookieParameters[$name])) {
                $cookie[$name] = $value;
            } else {
                $query[$name] = $value;
            }
        }

        $path = $this->castParameters($normalized->pathParameters, $path);
        $query = $this->castParameters($normalized->queryParameters, $query);
        $header = $this->castParameters($normalized->headerParameters, $header);
        $cookie = $this->castParameters($normalized->cookieParameters, $cookie);

        $serializedPath = $this->parameterSerializer->serialize('path', $normalized->pathParameters, $path);
        foreach ($serializedPath as $name => $value) {
            $style = $normalized->pathParameters[$name]['style'] ?? 'simple';
            $replacement = $style === 'simple' ? urlencode($value) : $value;
            // matrix and label include the prefix in the serialized value,
            // so we replace the template
            $urlPath = str_replace('{'.$name.'}', $replacement, $urlPath);
        }

        $url = $baseUrl.$urlPath;

        $serializedQuery = $this->parameterSerializer->serialize('query', $normalized->queryParameters, $query);
        $filteredQuery = array_filter($serializedQuery, fn ($val) => $val !== '');
        if (!empty($filteredQuery)) {
            $url .= '?'.implode('&', array_values($filteredQuery));
        }

        $request = $this->requestFactory->createRequest($method, $url);

        $serializedHeader = $this->parameterSerializer->serialize('header', $normalized->headerParameters, $header);
        foreach ($serializedHeader as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }

        $serializedCookie = $this->parameterSerializer->serialize('cookie', $normalized->cookieParameters, $cookie);
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
                'integer' => $this->typeCaster->asInteger($value),
                'number' => $this->typeCaster->asFloat($value),
                'string' => $this->typeCaster->asString($value),
                'boolean' => $this->typeCaster->asBoolean($value),
                'array' => $this->typeCaster->asArray($value),
                default => $value,
            };
        } catch (Exception) {
            return $value;
        }
    }
}
