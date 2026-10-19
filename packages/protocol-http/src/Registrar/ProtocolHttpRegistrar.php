<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Http\Registrar;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Engine\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Framework-agnostic registration of the HTTP/OpenAPI vertical slice into the
 * core SPI registries. Used by the Laravel provider and by CLI/umbrella wiring.
 *
 * Only contracts-side types appear in the signature (H1/F1.4: no
 * runner-package reference below the runner package) — the host wires the
 * runner-side registries (`OperationExecutorRegistry`,
 * `ResponseValidatorDispatcher`) from the accessors below.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtocolHttpRegistrar
{
    public function __construct(
        private readonly OpenApiDocumentLoader $loader,
        private readonly OpenApiOperationResolver $operationResolver,
        private readonly OpenApiExecutorInterface $openApiExecutor,
        private readonly OutputExtractorInterface $outputExtractor,
        private readonly ResponseValidatorInterface $schemaValidator,
        private readonly EvaluationEngineInterface $evaluationEngine,
        private readonly PendingCorrelationRegistryInterface $pendingCorrelations,
        private readonly HttpClientInterface $httpClient,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly bool $strictValidationDefault = false,
        private readonly bool $idempotencyEnabled = false,
        private readonly string $idempotencyHeader = 'Idempotency-Key',
    ) {}

    /**
     * The arazzo.plugins.* tag vocabulary the Laravel host registers. Owned
     * here so the concrete protocol node names never leak into host packages:
     * the service provider consumes this vocabulary instead of naming the
     * step-executor concretes itself.
     *
     * @return array{'arazzo.plugins.*': list<class-string>, 'arazzo.plugins.operation-executor': list<class-string>}
     */
    public static function tags(): array
    {
        return [
            'arazzo.plugins.*' => [
                OpenApiSourceNormalizer::class,
                HttpStepExecutor::class,
                AsyncApiStepExecutor::class,
                ResponseSchemaValidator::class,
            ],
            'arazzo.plugins.operation-executor' => [
                HttpStepExecutor::class,
                AsyncApiStepExecutor::class,
            ],
        ];
    }

    public function normalizer(): OpenApiSourceNormalizer
    {
        return new OpenApiSourceNormalizer(
            $this->loader,
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );
    }

    /**
     * The step-protocol plugins, NOT DefaultOpenApiExecutor: the registry
     * holds OperationExecutorPluginInterface implementations keyed by protocol.
     *
     * @return list<OperationExecutorPluginInterface>
     */
    public function executors(): array
    {
        return [$this->httpExecutor(), $this->asyncExecutor()];
    }

    /** @return list<ResponseValidatorInterface> */
    public function validators(): array
    {
        return [new ResponseSchemaValidator($this->operationResolver)];
    }

    public function registerInto(?SourceNormalizerRegistryInterface $sourceRegistry = null): void
    {
        $sourceRegistry?->register($this->normalizer());
    }

    private function httpExecutor(): HttpStepExecutor
    {
        $injector = $this->idempotencyEnabled
            ? new IdempotencyKeyInjector(
                enabledDefault: $this->idempotencyEnabled,
                headerDefault: $this->idempotencyHeader,
            )
            : null;

        return new HttpStepExecutor(
            $this->openApiExecutor,
            $this->operationResolver,
            $this->evaluationEngine,
            $this->outputExtractor,
            $this->schemaValidator,
            strictValidationDefault: $this->strictValidationDefault,
            injector: $injector,
        );
    }

    private function asyncExecutor(): AsyncApiStepExecutor
    {
        return new AsyncApiStepExecutor(
            $this->pendingCorrelations,
            $this->evaluationEngine,
            $this->httpClient,
            $this->requestFactory,
        );
    }
}
