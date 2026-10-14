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
 * A WSDL step references its operation through `operationName` (Arazzo
 * Specification, Step Object: `operationId`/`operationPath` MUST NOT reference
 * a `wsdl` source). The shared source-name grammar accepts a plain operation
 * name, `$sourceDescriptions.<name>.<op>` and
 * `{$sourceDescriptions.<name>.url}#<op>`. `operationId` is additionally
 * honoured as an internal/back-compat fallback for callers that predate the
 * `operationName` field. The resolver restricts to WSDL-typed sources to keep
 * supportsStep() collision-safe against the HTTP/AsyncAPI executors, then
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

        // Spec: a `wsdl` source MUST be referenced with `operationName`;
        // `operationId` is honoured as an internal/back-compat fallback.
        $operationReference = $step->target->operationName ?? $step->target->operationId;
        if ($operationReference === null) {
            return null;
        }

        if (str_contains($operationReference, '#')) {
            $parts = explode('#', $operationReference, 2);
            if (!str_starts_with($parts[0], self::SOURCE_PREFIX) || !str_ends_with($parts[0], self::SOURCE_URL_SUFFIX)) {
                return null;
            }

            $sourceName = substr($parts[0], strlen(self::SOURCE_PREFIX), -strlen(self::SOURCE_URL_SUFFIX));
            $reference = $parts[1];
        } elseif (preg_match(self::DOTTED_REFERENCE, $operationReference, $matches) === 1) {
            $sourceName = $matches[1];
            $reference = $matches[2];
        } else {
            $nonArazzo = array_values(array_filter(
                $document->sourceDescriptions,
                static fn (SourceDescription $source): bool => $source->type !== SourceType::Arazzo,
            ));
            if (count($nonArazzo) !== 1) {
                return null;
            }

            $sourceName = $nonArazzo[0]->name;
            $reference = $operationReference;
        }

        $source = self::findSource($document, $sourceName);

        return $source !== null && $source->type === SourceType::Wsdl ? [$sourceName, $reference] : null;
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
