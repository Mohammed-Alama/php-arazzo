<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransferMapper;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Operation protocol plugin executing steps bound to a WSDL-described SOAP
 * operation (plan F4.2).
 *
 * Registered first (priority 50, below HttpStepExecutor's 100) so a WSDL step
 * is claimed here rather than falling through to the generic HTTP executor;
 * supports() only claims steps that resolve to a WSDL source.
 *
 * Outcome mapping (plan F4.2): SOAP faults (HTTP 200 with a fault envelope)
 * and transport failures become failed StepExecutionOutcomes carrying
 * `{'soap.faultcode', 'soap.faultstring'}` in the transfer meta; success
 * exposes the SoapResponseTransfer as `responseBody['transfer']`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapOperationExecutor implements OperationExecutorPluginInterface
{
    /**
     * Below HttpStepExecutor::PLUGIN_PRIORITY (100): the registry tries lower
     * priorities first, so WSDL steps land here before the generic executor.
     */
    public const PLUGIN_PRIORITY = 50;

    public function __construct(
        private SoapOperationResolverInterface $operationResolver,
        private SoapRequestBuilder $requestBuilder,
        private HttpClientInterface $httpClient,
        private SoapResponseTransferMapper $responseMapper,
        private SoapFaultMapper $faultMapper,
    ) {}

    public function name(): string
    {
        return 'soap';
    }

    public function priority(): int
    {
        return self::PLUGIN_PRIORITY;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->target->action === null && $this->operationResolver->supportsStep($step, $document);
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $resolved = $this->resolve($step, $document);

        try {
            $request = $this->requestBuilder->build($step, $context, $document, $resolved, $executionId);
            $response = $this->httpClient->sendRequest($request, $step->flow->timeout);
        } catch (ClientExceptionInterface $e) {
            // Transport-level failures become a synthetic 500 (HTTP precedent)
            // so failureActions apply identically to the sync adapter.
            return $this->faultMapper->map(
                new SoapResponseTransfer(status: 500, headers: [], rawBody: '', meta: ['error' => $e->getMessage()]),
                'transport',
            );
        }

        $transfer = $this->responseMapper->map($response);
        $meta = $transfer->meta();

        if (isset($meta['soap.faultcode']) || isset($meta['error'])) {
            return $this->faultMapper->map($transfer, isset($meta['soap.faultcode']) ? 'soap-fault' : 'transport');
        }

        return StepExecutionOutcome::resolved(
            statusCode: is_int($transfer->status()) ? $transfer->status() : 500,
            outputs: [],
            responseBody: ['transfer' => $transfer],
            responseHeaders: $transfer->headers(),
            rawBody: is_string($transfer->rawBody()) ? $transfer->rawBody() : null,
        );
    }

    private function resolve(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        try {
            return $this->operationResolver->resolve($step, $document);
        } catch (RuntimeException $e) {
            throw new ExecutionException(
                "SOAP resolution failed for step '{$step->stepId}': ".$e->getMessage(),
                '/',
                'execution.soap_resolution_failed',
                $e,
            );
        }
    }
}
