<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Normalizer\ResolvedOperation;
use Alama\Arazzo\Spec\OpenApiPayload;
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
        ResolvedOperation $resolvedOperation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        // Delegate for the real dispatch (records the request), then swap
        // in a fabricated response derived from the operation's own contract.
        $this->inner->execute($resolvedOperation, $payload, $requestInterceptor, $timeoutSeconds);

        [$status, $body] = self::synthesizeResponse($resolvedOperation->normalized->responses ?? []);

        foreach ($this->referencedBodyFields as $field) {
            $body[$field] = $body[$field] ?? false;
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body) ?: '{}',
        );
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<string, mixed>
     */
    /** @return array{0: int, 1: array<string, mixed>} */
    public static function synthesizeResponse(array $responses): array
    {
        // Prefer declared 2xx codes, then any other explicit code (e.g. a
        // 302 redirect step), then default.
        $candidates = array_keys(array_filter(
            $responses,
            fn ($v, $k): bool => is_string($k) && preg_match('/^[1-5]\d\d$/', $k) === 1,
            ARRAY_FILTER_USE_BOTH,
        ));

        usort($candidates, fn (string $a, string $b): int => ((int) $a < 300 ? 0 : 1) - ((int) $b < 300 ? 0 : 1) ?: strcmp($a, $b));

        foreach ([...$candidates, 'default'] as $code) {
            if (!isset($responses[$code]) || !is_array($responses[$code])) {
                continue;
            }

            $content = $responses[$code]['content'] ?? null;

            if (!is_array($content)) {
                continue;
            }

            $statusCode = ctype_digit((string) $code) ? (int) $code : 200;

            foreach ($content as $mediaType => $mediaTypeObject) {
                if (!str_starts_with((string) $mediaType, 'application/json') || !is_array($mediaTypeObject)) {
                    continue;
                }

                $schema = $mediaTypeObject['schema'] ?? null;

                if (is_array($schema)) {
                    $instance = self::instanceFromSchema($schema);

                    if ($instance !== []) {
                        return [$statusCode, $instance];
                    }
                }

                // Declared success response without JSON content: still
                // report its status so `$statusCode == 2xx` criteria pass.
                return [$statusCode, []];
            }
        }

        return [200, []];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function instanceFromSchema(array $schema): array
    {
        return ConformanceFabricator::objectFromSchema($schema);
    }
}
