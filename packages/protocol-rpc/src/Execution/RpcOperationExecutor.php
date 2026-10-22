<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Execution;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\OperationResolverInterface;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcFieldBag;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransfer;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransferMapper;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Operation protocol plugin executing steps bound to a protobuf-described RPC
 * method (plan F5.1).
 *
 * Registered at priority 50 (below HttpStepExecutor's 100) so an RPC step is
 * claimed here rather than falling through to the generic HTTP executor;
 * supports() only claims steps that resolve to a protobuf source.
 *
 * Outcome mapping (plan F5.1): RPC errors (grpc-status / X-Twirp-Error /
 * Connect-Error-Code) and transport failures become failed StepExecutionOutcomes
 * carrying the RPC error keys in the transfer meta; success exposes the
 * RpcResponseTransfer as `responseBody['transfer']`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcOperationExecutor implements OperationExecutorPluginInterface
{
    /**
     * Below HttpStepExecutor::PLUGIN_PRIORITY (100): the registry tries lower
     * priorities first, so RPC steps land here before the generic executor.
     */
    public const PLUGIN_PRIORITY = 50;

    public function __construct(
        private OperationResolverInterface $operationResolver,
        private RpcRequestBuilder $requestBuilder,
        private HttpClientInterface $httpClient,
        private RpcResponseTransferMapper $responseMapper,
        private RpcFailureMapper $failureMapper,
    ) {}

    public function name(): string
    {
        return 'rpc';
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
            $request = $this->requestBuilder->build($step, $context, $document, $resolved);
            $response = $this->httpClient->sendRequest($request, $step->flow->timeout);
        } catch (ClientExceptionInterface $e) {
            // Transport-level failures become a synthetic 500 (HTTP precedent)
            // so failureActions apply identically to the sync adapter.
            return $this->failureMapper->map(
                new RpcResponseTransfer(status: 500, headers: [], rawBody: '', views: [], meta: ['error' => $e->getMessage()]),
                'transport',
            );
        }

        $protocol = $resolved->rpcProtocol;
        if ($protocol === null) {
            throw new ExecutionException(
                "RPC step '{$step->stepId}' does not declare an rpcProtocol; expected one of grpc, grpc-web, twirp, connect.",
                '/',
                'execution.rpc_protocol_missing',
            );
        }

        $bags = $resolved->normalized->requestBodies['application/protobuf'] ?? null;
        $responseFields = RpcFieldBag::normalize(is_array($bags) ? ($bags['responseFields'] ?? []) : []);
        $transfer = $this->responseMapper->map($response, $protocol, $responseFields);
        $meta = $transfer->meta();

        if (isset($meta['rpc.error']) || isset($meta['error'])) {
            return $this->failureMapper->map($transfer, isset($meta['rpc.error']) ? 'rpc-error' : 'transport');
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
                "RPC resolution failed for step '{$step->stepId}': ".$e->getMessage(),
                '/',
                'execution.rpc_resolution_failed',
                $e,
            );
        }
    }
}
