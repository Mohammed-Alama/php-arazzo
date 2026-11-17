<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Deterministic response fabricator for the OAI corpus: answers every
 * request with the FIRST declared success response of the target
 * operation, synthesizing a body from `example` / `default` / property
 * primitives so successCriteria that assert on body fields (e.g.
 * `$[?@.access_token != null]`) can pass without any network I/O.
 */
final class FakerOpenApiExecutor implements OpenApiExecutorInterface
{
    /**
     * Top-level body fields referenced anywhere in the Arazzo document via
     * `$response.body#/FIELD` - servers must return them for the workflow
     * to be satisfiable even when the OpenAPI response omits a schema.
     *
     * @param  list<string>  $referencedBodyFields
     */
    public function __construct(
        private readonly OpenApiExecutorInterface $inner,
        private readonly array $referencedBodyFields = [],
    ) {}

    /** @return list<string> */
    public static function referencedBodyFields(string $arazzoYaml): array
    {
        $names = [];

        if (preg_match_all('/\$response\.body#\/([A-Za-z0-9_-]+)/', $arazzoYaml, $matches) >= 1) {
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    public function execute(
        OpenApiOperationHandle $resolvedOperation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        // Delegate for the real dispatch (records the request), then swap
        // in a fabricated response derived from the operation's own contract.
        $this->inner->execute($resolvedOperation, $payload, $requestInterceptor, $timeoutSeconds);

        [$status, $body, $rootType] = self::synthesizeResponse($resolvedOperation->operation->normalized->responses ?? []);

        // Only object-rooted responses can carry named fields. A reference
        // such as `$response.body#/0/id` addresses an array element, so
        // injecting the field names would turn a list into a map and make the
        // fabricated body violate its own declared schema.
        if ($rootType !== 'array') {
            foreach ($this->referencedBodyFields as $field) {
                if (ctype_digit($field)) {
                    continue;
                }

                $body[$field] = $body[$field] ?? false;
            }
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body) ?: '{}',
        );
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    /** @return array{0: int, 1: array<string, mixed>, 2: string|null} */
    public static function synthesizeResponse(array $responses): array
    {
        // Prefer declared 2xx codes, then any other explicit code (e.g. a
        // 302 redirect step), then default. The normalizer keeps literal
        // status codes as integer keys, so both key types are candidates.
        $candidates = [];

        foreach (array_keys($responses) as $key) {
            if (preg_match('/^[1-5]\d\d$/', (string) $key) === 1) {
                $candidates[] = (string) $key;
            }
        }

        usort($candidates, fn (string $a, string $b): int => ((int) $a < 300 ? 0 : 1) - ((int) $b < 300 ? 0 : 1) ?: strcmp($a, $b));

        foreach ([...$candidates, 'default'] as $code) {
            if (!isset($responses[$code]) || !is_array($responses[$code])) {
                continue;
            }

            $statusCode = ctype_digit((string) $code) ? (int) $code : 200;
            $content = $responses[$code]['content'] ?? null;

            if (!is_array($content)) {
                // A declared response with no content still reports its status
                // (redirects, 204s); falling through to `default` would report
                // an error status the workflow never asked for.
                return [$statusCode, [], null];
            }

            foreach ($content as $mediaType => $mediaTypeObject) {
                if (!str_starts_with((string) $mediaType, 'application/json') || !is_array($mediaTypeObject)) {
                    continue;
                }

                $schema = $mediaTypeObject['schema'] ?? null;

                if (is_array($schema)) {
                    $rootType = is_string($schema['type'] ?? null) ? $schema['type'] : null;
                    $instance = self::instanceFromSchema($schema);

                    return [$statusCode, $instance, $rootType];
                }

                // Declared success response without JSON content: still
                // report its status so `$statusCode == 2xx` criteria pass.
                return [$statusCode, [], null];
            }

            return [$statusCode, [], null];
        }

        return [200, [], null];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function instanceFromSchema(array $schema): array
    {
        if (($schema['type'] ?? null) === 'array') {
            return ConformanceFabricator::arrayFromSchema($schema);
        }

        return ConformanceFabricator::objectFromSchema($schema);
    }
}
