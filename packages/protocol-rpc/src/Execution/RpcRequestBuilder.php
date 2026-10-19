<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\RequestCompiler;
use Alama\Arazzo\Engine\Execution\ReusableParameterResolver;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcFieldBag;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcWireProfile;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the POST request for a resolved RPC operation (plan F5.1).
 *
 * - endpoint: from step parameters named `url` or `endpoint`, falling back
 *   to the operation's resolvedServerUrl (the host wires the endpoint there
 *   when the source URL is not the target).
 * - content-type: the canonical wire profile value per rpcProtocol variant.
 * - body: the protobuf-encoded request message, gRPC-framed (5-byte prefix)
 *   for grpc/grpc-web and raw for twirp/connect. Message fields are the
 *   resolved step inputs minus the endpoint overrides and minus `in: metadata`
 *   parameters (which become request headers).
 *
 * rpcProtocol lives on the step target; a null discriminator here is an
 * authoring error naming the four supported variants.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcRequestBuilder
{
    /** @var list<string> */
    private const OVERRIDE_PARAMETERS = ['url', 'endpoint'];

    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private EvaluationEngineInterface $evaluationEngine,
        private RpcMessageCodec $codec,
    ) {}

    public function build(
        Step $step,
        WorkflowContext $context,
        ArazzoDocument $document,
        ResolvedOperation $resolved,
        string $executionId,
    ): RequestInterface {
        $protocol = $resolved->rpcProtocol;
        if ($protocol === null) {
            throw new ExecutionException(
                "RPC step '{$step->stepId}' does not declare an rpcProtocol; expected one of grpc, grpc-web, twirp, connect.",
                '/',
                'execution.rpc_protocol_missing',
            );
        }

        $bags = $resolved->normalized->requestBodies['application/protobuf'] ?? null;
        if (!is_array($bags) || !is_array($bags['requestFields'] ?? null)) {
            throw new ExecutionException(
                "RPC step '{$step->stepId}' has no application/protobuf request body definition.",
                '/',
                'execution.rpc_request_body_missing',
            );
        }

        $compiled = (new RequestCompiler($this->evaluationEngine))->compile($step, $document, $context);
        $inputs = $compiled['resolvedInputs'];

        $target = $this->override($inputs, 'url') ?? $this->override($inputs, 'endpoint') ?? $resolved->normalized->resolvedServerUrl;
        if ($target === null || $target === '') {
            throw new ExecutionException(
                "RPC step '{$step->stepId}' has no target endpoint.",
                '/',
                'execution.rpc_target_missing',
            );
        }

        $metadataHeaders = $this->metadataHeaders($step, $document, $inputs);
        $requestFields = RpcFieldBag::normalize($bags['requestFields']);
        $messageFields = array_diff_key($inputs, array_flip(self::OVERRIDE_PARAMETERS), $metadataHeaders);

        $profile = RpcWireProfile::fromProtocol($protocol);
        $message = $this->codec->encodeBinary($requestFields, $messageFields);
        $body = $profile->framesMessages() ? $this->codec->frame($message) : $message;

        $request = $this->requestFactory->createRequest('POST', $target);
        $request = $request->withHeader('Content-Type', $profile->requestContentType());

        foreach ($metadataHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody($this->streamFactory->createStream($body));
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function override(array $inputs, string $name): ?string
    {
        $value = $inputs[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Every `in: metadata` parameter becomes a request header; its evaluated
     * value is taken from the compiler's resolved inputs.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, string>
     */
    private function metadataHeaders(Step $step, ArazzoDocument $document, array $inputs): array
    {
        $headers = [];
        $parameters = (new ReusableParameterResolver())->resolve($step->io->parameters, $document);

        foreach ($parameters as $parameter) {
            if ($parameter->in !== ParameterIn::Metadata) {
                continue;
            }

            $value = $inputs[$parameter->name] ?? $parameter->value;
            if (is_scalar($value)) {
                $headers[$parameter->name] = (string) $value;
            }
        }

        return $headers;
    }
}
