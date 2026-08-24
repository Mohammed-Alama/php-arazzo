<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Resolver;

use Alama\Arazzo\Runner\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;
use cebe\openapi\spec\Operation;
use RuntimeException;

class OpenApiOperationResolver
{
    public function __construct(
        private OpenApiDocumentLoader $loader,
        private OpenApiVersionDetector $versionDetector,
        private OpenApi30Normalizer $normalizer30,
        private OpenApi31Normalizer $normalizer31,
    ) {
    }

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        $opId = $step->operationId;
        $opPath = $step->operationPath;

        if (!$opId && !$opPath) {
            throw new RuntimeException("Step '{$step->stepId}' must have either operationId or operationPath.");
        }

        $sourceName = null;
        $operationReference = null;
        $isPath = false;

        if ($opPath) {
            $isPath = true;
            $parts = explode('#', $opPath, 2);
            if (count($parts) !== 2 || !str_starts_with($parts[0], '{$sourceDescriptions.') || !str_ends_with($parts[0], '.url}')) {
                throw new RuntimeException("Invalid operationPath format in step '{$step->stepId}'. Expected {\$sourceDescriptions.NAME.url}#...");
            }
            $sourceName = substr($parts[0], 21, -5);
            $operationReference = $parts[1];
        } else {
            if (str_contains($opId, '#')) {
                $parts = explode('#', $opId, 2);
                if (!str_starts_with($parts[0], '{$sourceDescriptions.') || !str_ends_with($parts[0], '.url}')) {
                    throw new RuntimeException("Invalid operationId format in step '{$step->stepId}'. Expected {\$sourceDescriptions.NAME.url}#...");
                }
                $sourceName = substr($parts[0], 21, -5);
                $operationReference = $parts[1];
            } elseif (preg_match('/^\$sourceDescriptions\.([^.]+)\.(.+)$/', $opId, $m) === 1) {
                // Spec grammar: `$sourceDescriptions.NAME.OPERATION_ID` (dotted,
                // no braces) - used by the official OAI examples.
                $sourceName = $m[1];
                $operationReference = $m[2];
            } else {
                $nonArazzoSources = array_filter($document->sourceDescriptions, fn (SourceDescription $s) => $s->type !== SourceType::Arazzo);
                if (count($nonArazzoSources) !== 1) {
                    throw new RuntimeException("Plain operationId '{$opId}' used in step '{$step->stepId}' but there are multiple non-Arazzo sources. Please use source-qualified operationId.");
                }
                $source = reset($nonArazzoSources);
                $sourceName = $source->name;
                $operationReference = $opId;
            }
        }

        $targetSource = null;
        foreach ($document->sourceDescriptions as $source) {
            if ($source->name === $sourceName) {
                $targetSource = $source;
                break;
            }
        }

        if (!$targetSource) {
            throw new RuntimeException("Source '{$sourceName}' not found in document for step '{$step->stepId}'.");
        }

        $openApi = $this->loader->load($targetSource, getcwd() ?: '');
        if (!$openApi) {
            throw new RuntimeException("Failed to load source '{$sourceName}'.");
        }

        $encoded = json_encode($openApi->getSerializableData());
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode OpenAPI document');
        }
        $rawDocument = json_decode($encoded, true);
        if (!is_array($rawDocument)) {
            throw new RuntimeException('Failed to decode OpenAPI document into an array');
        }
        /** @var array<string, mixed> $rawDocument */

        // Fail fast on unsupported source versions before any operation lookup.
        $detected = $this->versionDetector->detect($rawDocument);
        if (!in_array($detected, ['3.0', '3.1'], true)) {
            throw UnsupportedSourceVersionException::forVersion($detected, (string) $sourceName);
        }

        /** @var string|null $foundPath */
        $foundPath = null;
        /** @var string|null $foundMethod */
        $foundMethod = null;
        /** @var Operation|null $cebeOperation */
        $cebeOperation = null;

        if ($isPath) {
            // JSON Pointer segments are split BEFORE unescaping so multi-
            // segment paths like /pet/findByStatus (~1pet~1findByStatus)
            // stay a single PATH token.
            $refParts = explode('/', ltrim($operationReference, '/'));
            $unescaped = array_map(
                fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
                $refParts,
            );

            if (count($unescaped) < 3 || $unescaped[0] !== 'paths') {
                throw new RuntimeException("Invalid operationPath reference '{$operationReference}'. Expected /paths/PATH/METHOD.");
            }

            $foundMethod = strtolower((string) array_pop($unescaped));
            array_shift($unescaped);
            $foundPath = implode('/', $unescaped);

            $pathItem = $openApi->paths->getPath($foundPath);
            if ($pathItem && isset($pathItem->{$foundMethod})) {
                $op = $pathItem->{$foundMethod};
                if ($op instanceof Operation) {
                    $cebeOperation = $op;
                }
            }
        } else {
            foreach ($openApi->paths as $path => $pathItem) {
                foreach (['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'] as $method) {
                    if (isset($pathItem->{$method})) {
                        $op = $pathItem->{$method};
                        /** @phpstan-ignore-next-line */
                        if ($op instanceof Operation && $op->operationId === $operationReference) {
                            if (is_string($path)) {
                                $foundPath = $path;
                            } elseif (is_scalar($path)) {
                                $foundPath = (string) $path;
                            }
                            $foundMethod = $method;
                            $cebeOperation = $op;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$foundPath || !$foundMethod || !$cebeOperation) {
            throw new RuntimeException("Operation '{$operationReference}' not found in source '{$sourceName}'.");
        }

        $normalizer = $detected === '3.0' ? $this->normalizer30 : $this->normalizer31;

        $normalized = $normalizer->normalize($rawDocument, (string) $foundPath, (string) $foundMethod);

        return new ResolvedOperation(
            $targetSource,
            $normalized,
            $openApi,
            $rawDocument,
            $cebeOperation,
        );
    }
}
