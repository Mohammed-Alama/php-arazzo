<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Registrar;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcFailureMapper;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcOperationExecutor;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcRequestBuilder;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use Alama\Arazzo\Protocol\Rpc\Protocol\ProtobufOperationResolver;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransferMapper;

/**
 * Framework-agnostic registration of the RPC vertical slice into the core SPI
 * registries (plan F5.2). Used by the Laravel provider and by CLI/umbrella
 * wiring.
 *
 * Only contracts-side types appear in the public signature — the host wires
 * the runner-side registries (`OperationExecutorRegistry`) from the accessors
 * below. The `.proto` fetcher is host-selected (F5.1 ledger): a file
 * deployment wires LocalFetcher, a remote one an HttpFetcher built in the host
 * layer; the fetcher feeds the operation resolver, not the registrar itself.
 *
 * One executor serves all four wire variants (grpc/grpc-web/twirp/connect):
 * the step's rpcProtocol discriminator selects the wire profile inside the
 * builder and mapper, so the slice contributes exactly one plugin.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcRegistrar
{
    public function __construct(
        private readonly ProtoSourceNormalizer $normalizer,
        private readonly HttpClientInterface $httpClient,
        private readonly RpcRequestBuilder $requestBuilder,
        private readonly ProtobufOperationResolver $operationResolver,
    ) {}

    /**
     * The RPC contribution to the `arazzo.plugins.*` tag vocabulary. Owned
     * here so the concrete protocol node names never leak into host packages:
     * the service provider consumes this vocabulary instead of naming the
     * step-executor concretes itself.
     *
     * @return array{'arazzo.plugins.*': list<class-string>, 'arazzo.plugins.rpc-operation-executor': list<class-string>}
     */
    public static function tags(): array
    {
        return [
            'arazzo.plugins.*' => [
                ProtoSourceNormalizer::class,
                RpcOperationExecutor::class,
            ],
            'arazzo.plugins.rpc-operation-executor' => [
                RpcOperationExecutor::class,
            ],
        ];
    }

    public function normalizer(): ProtoSourceNormalizer
    {
        return $this->normalizer;
    }

    /**
     * The step-protocol plugin: the runner registry holds
     * OperationExecutorPluginInterface implementations keyed by protocol. This
     * slice contributes exactly one (it dispatches all four wire variants
     * internally), so the list is typed to it.
     *
     * @return list<RpcOperationExecutor>
     */
    public function executors(): array
    {
        return [new RpcOperationExecutor(
            $this->operationResolver,
            $this->requestBuilder,
            $this->httpClient,
            new RpcResponseTransferMapper(new RpcMessageCodec()),
            new RpcFailureMapper(),
        )];
    }

    public function registerInto(?SourceNormalizerRegistryInterface $sourceRegistry = null): void
    {
        $sourceRegistry?->register($this->normalizer);
    }
}
