<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Protocol;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\OperationResolverInterface;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceFetcher;
use RuntimeException;

/**
 * OperationResolverInterface over a described protobuf source (plan F5.1).
 *
 * RPC step references are always source-qualified — the spec PR #556 pattern
 * mandates the `$sourceDescriptions.<name>.<Service>/<Method>` form — so
 * unlike WSDL there is no plain-operationId fallback. supportsStep() is the
 * side-effect-free reference grammar; resolve() additionally fetches and
 * normalises the `.proto`, then re-wraps the indexed operation with the
 * step's rpcProtocol + rpcMethod discriminator (step-object properties).
 *
 * The fetcher is injected by the host (F5.2) so protocol-rpc stays
 * vendor-free: a file deployment wires LocalFetcher, a remote one an
 * HttpFetcher built in the host layer.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtobufOperationResolver implements OperationResolverInterface
{
    private const DOTTED_REFERENCE = '/^\$sourceDescriptions\.([A-Za-z0-9_-]+)\.([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\/([A-Za-z_][A-Za-z0-9_]*)$/';

    public function __construct(
        private SourceFetcher $fetcher,
        private ProtoSourceNormalizer $normalizer,
    ) {}

    public function supportsStep(Step $step, ArazzoDocument $document): bool
    {
        return self::operationReference($step, $document) !== null;
    }

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        $reference = self::operationReference($step, $document);
        if ($reference === null) {
            throw new RuntimeException("Step '{$step->stepId}' does not reference a protobuf source.");
        }

        [$sourceName, $service, $method] = $reference;
        $source = self::findSource($document, $sourceName);
        if ($source === null) {
            throw new RuntimeException("Source '{$sourceName}' not found in the document.");
        }

        $raw = $this->fetcher->fetch($source->url, getcwd() ?: '');
        $index = $this->normalizer->normalize($source, $raw);

        $operationReference = $service.'/'.$method;
        $resolved = $index[$operationReference] ?? null;
        if ($resolved === null) {
            throw new RuntimeException("RPC method '{$operationReference}' not found in source '{$sourceName}'.");
        }

        return new ResolvedOperation(
            source: $resolved->source,
            normalized: $resolved->normalized,
            rpcProtocol: $step->target->rpcProtocol,
            rpcMethod: $operationReference,
        );
    }

    /**
     * Extracts [sourceName, service, method] when the step names a protobuf
     * source through the source-qualified RPC grammar, else null.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public static function operationReference(Step $step, ArazzoDocument $document): ?array
    {
        if ($step->target->action !== null) {
            return null;
        }

        $rpcMethod = $step->target->rpcMethod;
        if ($rpcMethod === null || preg_match(self::DOTTED_REFERENCE, $rpcMethod, $matches) !== 1) {
            return null;
        }

        $source = self::findSource($document, $matches[1]);

        return $source !== null && $source->type === SourceType::Protobuf ? [$matches[1], $matches[2], $matches[3]] : null;
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
