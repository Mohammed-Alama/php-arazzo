<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Resolver;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\OpenApiDocumentLoader;
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

        $rawDocument = json_decode(json_encode($openApi->getSerializableData()), true);

        $foundPath = null;
        $foundMethod = null;
        $cebeOperation = null;

        if ($isPath) {
            $refParts = explode('/', ltrim($operationReference, '/'));
            if (count($refParts) !== 3 || $refParts[0] !== 'paths') {
                throw new RuntimeException("Invalid operationPath reference '{$operationReference}'. Expected /paths/PATH/METHOD.");
            }
            $foundPath = str_replace(['~1', '~0'], ['/', '~'], $refParts[1]);
            $foundMethod = strtolower($refParts[2]);

            $pathItem = $openApi->paths->getPath($foundPath);
            if ($pathItem && isset($pathItem->{$foundMethod})) {
                $cebeOperation = $pathItem->{$foundMethod};
            }
        } else {
            foreach ($openApi->paths as $path => $pathItem) {
                foreach (['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'] as $method) {
                    if (isset($pathItem->{$method})) {
                        $op = $pathItem->{$method};
                        if ($op instanceof Operation && $op->operationId === $operationReference) {
                            $foundPath = $path;
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

        $version = $this->versionDetector->detect($rawDocument);

        $normalizer = match ($version) {
            '2.0', '3.0' => $this->normalizer30,
            '3.1' => $this->normalizer31,
            default => throw new RuntimeException("Unsupported OpenAPI version: {$version}"),
        };

        $normalized = $normalizer->normalize($rawDocument, $foundPath, $foundMethod);

        return new ResolvedOperation(
            $targetSource,
            $normalized,
            $openApi,
            $rawDocument,
            $cebeOperation,
        );
    }
}
