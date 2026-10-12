<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Registrar;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Protocol\Soap\Execution\SoapFaultMapper;
use Alama\Arazzo\Protocol\Soap\Execution\SoapOperationExecutor;
use Alama\Arazzo\Protocol\Soap\Execution\SoapRequestBuilder;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use Alama\Arazzo\Protocol\Soap\Resolver\XPathReplacementTargetResolver;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransferMapper;
use Alama\Arazzo\Protocol\Soap\Validation\XsdResponseValidator;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;

/**
 * Framework-agnostic registration of the SOAP vertical slice into the core
 * SPI registries (plan F4.6). Used by the Laravel provider and by CLI/umbrella
 * wiring.
 *
 * Only contracts-side types appear in the public signature — the host wires
 * the runner-side registries (`OperationExecutorRegistry`,
 * `ResponseValidatorDispatcher`) from the accessors below. The WSDL fetcher is
 * host-selected (F4.2 ledger): a file deployment wires LocalFetcher, a remote
 * one an HttpFetcher built in the host layer.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapRegistrar
{
    public function __construct(
        private readonly WsdlSourceNormalizer $normalizer,
        private readonly HttpClientInterface $httpClient,
        private readonly SoapRequestBuilder $requestBuilder,
        private readonly WsdlOperationResolver $operationResolver,
    ) {}

    /**
     * The SOAP contribution to the `arazzo.plugins.*` tag vocabulary. Owned
     * here so the concrete protocol node names never leak into host packages:
     * the service provider consumes this vocabulary instead of naming the
     * step-executor concretes itself.
     *
     * @return array{'arazzo.plugins.*': list<class-string>, 'arazzo.plugins.soap-operation-executor': list<class-string>}
     */
    public static function tags(): array
    {
        return [
            'arazzo.plugins.*' => [
                WsdlSourceNormalizer::class,
                SoapOperationExecutor::class,
                XsdResponseValidator::class,
                XPathReplacementTargetResolver::class,
            ],
            'arazzo.plugins.soap-operation-executor' => [
                SoapOperationExecutor::class,
            ],
        ];
    }

    public function normalizer(): WsdlSourceNormalizer
    {
        return $this->normalizer;
    }

    /**
     * The step-protocol plugin: the runner registry holds
     * OperationExecutorPluginInterface implementations keyed by protocol. This
     * slice contributes exactly one, so the list is typed to it.
     *
     * @return list<SoapOperationExecutor>
     */
    public function executors(): array
    {
        return [new SoapOperationExecutor(
            $this->operationResolver,
            $this->requestBuilder,
            $this->httpClient,
            new SoapResponseTransferMapper(),
            new SoapFaultMapper(),
        )];
    }

    /** @return list<XsdResponseValidator> */
    public function validators(): array
    {
        return [new XsdResponseValidator($this->operationResolver)];
    }

    public function registerInto(?SourceNormalizerRegistryInterface $sourceRegistry = null): void
    {
        $sourceRegistry?->register($this->normalizer);
    }
}
