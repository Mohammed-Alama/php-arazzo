<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Normalizer\Interfaces\OpenApiNormalizerInterface;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use InvalidArgumentException;

/**
 * Port (D2): turns a described source + raw content into an operation index.
 *
 * Index keys mirror OpenApiOperationResolver's accepted references:
 *   - plain operationId
 *   - "$sourceDescriptions.<name>.<operationId>"
 *   - "#/paths/<escaped-path>/<method>"
 *
 * Values are two-axis ResolvedOperation objects (mixed-compatible with the
 * contracts array signature). Privileged in `document` during D; relocated
 * to alama/arazzo-protocol-http in F1.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OpenApiSourceNormalizer implements SourceNormalizerInterface
{
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(
        private readonly OpenApiDocumentLoader $loader,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly OpenApi30Normalizer $normalizer30,
        private readonly OpenApi31Normalizer $normalizer31,
    ) {}

    public function name(): string
    {
        return 'openapi';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Openapi;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        /** @var array<string,mixed>|null $rawDocument */
        $rawDocument = json_decode($rawContent, true);
        if ($rawDocument === null) {
            throw new InvalidArgumentException('Failed to decode source content as JSON.');
        }

        $version = $this->detectVersion($rawDocument, $source);
        $openApi = $this->loadOpenApi($source);
        $normalizer = $this->chooseNormalizer($version);

        return $this->buildIndex($rawDocument, $openApi, $normalizer, $source);
    }

    /**
     * @param  array<string,mixed>  $rawDocument
     */
    private function detectVersion(array $rawDocument, SourceDescription $source): string
    {
        $version = $this->versionDetector->detect($rawDocument);
        if ($version === '2.0') {
            throw UnsupportedSourceVersionException::forVersion('2.0', (string) $source->name);
        }

        return $version;
    }

    private function loadOpenApi(SourceDescription $source): OpenApi
    {
        $basePath = $this->extractBasePath($source);
        $openApi = $this->loader->load($source, $basePath);
        if ($openApi === null) {
            throw new InvalidArgumentException('Failed to load OpenAPI document.');
        }

        return $openApi;
    }

    private function chooseNormalizer(string $version): OpenApiNormalizerInterface
    {
        return $version === '3.0' ? $this->normalizer30 : $this->normalizer31;
    }

    /**
     * @param  array<string,mixed>  $rawDocument
     * @return array<string, ResolvedOperation>
     */
    private function buildIndex(array $rawDocument, OpenApi $openApi, OpenApiNormalizerInterface $normalizer, SourceDescription $source): array
    {
        $index = [];

        /** @var array<string, mixed> $paths */
        $paths = $rawDocument['paths'] ?? [];
        foreach ($paths as $path => $pathItem) {
            $path = (string) $path;
            if (!is_array($pathItem)) {
                continue;
            }
            foreach (self::METHODS as $method) {
                if (!isset($pathItem[$method]) || !is_array($pathItem[$method])) {
                    continue;
                }
                $operation = $pathItem[$method];
                $operationId = $operation['operationId'] ?? null;

                $cebeOperation = $openApi->paths->getPath($path)?->{$method};
                if (!$cebeOperation instanceof Operation) {
                    continue;
                }

                $normalized = $normalizer->normalize($rawDocument, $path, $method);

                $resolved = new ResolvedOperation(
                    source: $source,
                    normalized: $normalized,
                    rpcProtocol: null,
                    operationName: null,
                    rpcMethod: null,
                    graphqlOperation: null,
                    interaction: null,
                );

                if ($operationId !== null && is_string($operationId)) {
                    $index[$operationId] = $resolved;
                    $index['$sourceDescriptions.'.$source->name.'.'.$operationId] = $resolved;
                }

                $pointer = $this->jsonPointerForPath((string) $path, $method);
                $index[$pointer] = $resolved;
            }
        }

        return $index;
    }

    private function extractBasePath(SourceDescription $source): string
    {
        $url = $source->url;
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);

            return dirname($path);
        }

        return '';
    }

    private function jsonPointerForPath(string $path, string $method): string
    {
        $segments = explode('/', ltrim($path, '/'));
        $escaped = array_map(
            static fn (string $seg): string => str_replace(['~', '/'], ['~0', '~1'], $seg),
            $segments,
        );

        return '#/paths/'.implode('/', $escaped).'/'.strtolower($method);
    }
}
