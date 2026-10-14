<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;

/**
 * Port (D2): the RPC sibling of OpenApiSourceNormalizer and
 * WsdlSourceNormalizer — turns a described protobuf source + raw `.proto`
 * content into a ResolvedOperation index for the source registry. Ships in
 * protocol-rpc because `.proto` reading is the DF11/D5 vendored-codec
 * exception: it never moves to arazzo-sources.
 *
 * Index keys mirror the RPC source-qualified reference grammar
 * (`$sourceDescriptions.<name>.<Service>/<Method>`, plain `<Service>/<Method>`
 * for a single source), matching ProtobufOperationResolver's accepted
 * references.
 *
 * rpcProtocol is a step-object property (spec PR #556), so the index carries
 * it as null and ProtobufOperationResolver re-wraps the operation with the
 * step's discriminator at resolve time.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtoSourceNormalizer implements SourceNormalizerInterface
{
    public function name(): string
    {
        return 'protobuf';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Protobuf;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        $parsed = (new ProtoParser())->parse($rawContent);

        $index = [];
        foreach ($parsed->services as $service) {
            foreach ($service->methods as $method) {
                $reference = $service->name.'/'.$method->name;
                $resolved = $this->resolveOperation($source, $parsed, $service->name, $method);
                $index[$reference] = $resolved;
                $index['$sourceDescriptions.'.$source->name.'.'.$reference] = $resolved;
            }
        }

        return $index;
    }

    private function resolveOperation(
        SourceDescription $source,
        ProtoDocument $parsed,
        string $service,
        ProtoMethod $method,
    ): ResolvedOperation {
        $normalized = new NormalizedOpenApiOperation(
            path: $service.'/'.$method->name,
            method: 'POST',
            resolvedServerUrl: $source->url,
            pathParameters: [],
            queryParameters: [],
            headerParameters: [],
            cookieParameters: [],
            requestBodies: [
                'application/protobuf' => [
                    'package' => $parsed->package,
                    'service' => $service,
                    'method' => $method->name,
                    'requestType' => $method->requestType,
                    'responseType' => $method->responseType,
                    'requestStreaming' => $method->requestStreaming,
                    'responseStreaming' => $method->responseStreaming,
                    'requestFields' => self::fieldBag($parsed->messages[$method->requestType] ?? []),
                    'responseFields' => self::fieldBag($parsed->messages[$method->responseType] ?? []),
                ],
            ],
            responses: [
                'application/protobuf' => [
                    'responseType' => $method->responseType,
                ],
            ],
        );

        return new ResolvedOperation($source, $normalized, rpcMethod: $service.'/'.$method->name);
    }

    /**
     * @param  list<ProtoField>  $fields
     * @return list<array{name: string, type: string, number: int, repeated: bool}>
     */
    private static function fieldBag(array $fields): array
    {
        return array_map(
            static fn (ProtoField $field): array => [
                'name' => $field->name,
                'type' => $field->type,
                'number' => $field->number,
                'repeated' => $field->repeated,
            ],
            $fields,
        );
    }
}
