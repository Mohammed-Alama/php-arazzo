<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\OperationResolverInterface;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use RuntimeException;

/**
 * OperationResolverInterface over a described WSDL source (plan F4.2).
 *
 * Reuses the source-name grammar shared with OpenApiOperationResolver (plain
 * operationId, `$sourceDescriptions.<name>.<op>`,
 * `{$sourceDescriptions.<name>.url}#<op>`), restricts to WSDL-typed sources to
 * keep supportsStep() collision-safe against the HTTP/AsyncAPI executors, then
 * fetches the raw WSDL and normalises it into the operation index.
 *
 * The fetcher is injected by the host (F4.6) so protocol-soap stays
 * vendor-free: a file deployment wires LocalFetcher, a remote one an
 * HttpFetcher built in the host layer.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlOperationResolver implements OperationResolverInterface
{
    private const SOURCE_PREFIX = '{$sourceDescriptions.';

    private const SOURCE_URL_SUFFIX = '.url}';

    private const DOTTED_REFERENCE = '/^\$sourceDescriptions\.([^.]+)\.(.+)$/';

    public function __construct(
        private SourceFetcher $fetcher,
        private WsdlSourceNormalizer $normalizer,
    ) {}

    public function supportsStep(Step $step, ArazzoDocument $document): bool
    {
        return self::operationReference($step, $document) !== null;
    }

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        $reference = self::operationReference($step, $document);
        if ($reference === null) {
            throw new RuntimeException("Step '{$step->stepId}' does not reference a WSDL source.");
        }

        [$sourceName, $operationReference] = $reference;
        $source = self::findSource($document, $sourceName);
        if ($source === null) {
            throw new RuntimeException("Source '{$sourceName}' not found in the document.");
        }

        $raw = $this->fetcher->fetch($source->url, getcwd() ?: '');
        $index = $this->normalizer->normalize($source, $raw);

        $resolved = $index[$operationReference] ?? null;
        if ($resolved === null) {
            throw new RuntimeException("WSDL operation '{$operationReference}' not found in source '{$sourceName}'.");
        }

        return $resolved;
    }

    /**
     * Extracts [sourceName, operationReference] when the step names a
     * WSDL-typed source, else null — the shared grammar supports() enforces
     * without fetching.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function operationReference(Step $step, ArazzoDocument $document): ?array
    {
        if ($step->target->action !== null) {
            return null;
        }

        $operationId = $step->target->operationId;
        if ($operationId === null) {
            return null;
        }

        if (str_contains($operationId, '#')) {
            $parts = explode('#', $operationId, 2);
            if (!str_starts_with($parts[0], self::SOURCE_PREFIX) || !str_ends_with($parts[0], self::SOURCE_URL_SUFFIX)) {
                return null;
            }

            $sourceName = substr($parts[0], strlen(self::SOURCE_PREFIX), -strlen(self::SOURCE_URL_SUFFIX));
            $operationReference = $parts[1];
        } elseif (preg_match(self::DOTTED_REFERENCE, $operationId, $matches) === 1) {
            $sourceName = $matches[1];
            $operationReference = $matches[2];
        } else {
            $nonArazzo = array_values(array_filter(
                $document->sourceDescriptions,
                static fn (SourceDescription $source): bool => $source->type !== SourceType::Arazzo,
            ));
            if (count($nonArazzo) !== 1) {
                return null;
            }

            $sourceName = $nonArazzo[0]->name;
            $operationReference = $operationId;
        }

        $source = self::findSource($document, $sourceName);

        return $source !== null && $source->type === SourceType::Wsdl ? [$sourceName, $operationReference] : null;
    }

    private static function findSource(ArazzoDocument $document, string $sourceName): ?SourceDescription
    {
        foreach ($document->sourceDescriptions as $candidate) {
            if ($candidate->name === $sourceName) {
                return $candidate;
            }
        }

        return null;
    }
}
