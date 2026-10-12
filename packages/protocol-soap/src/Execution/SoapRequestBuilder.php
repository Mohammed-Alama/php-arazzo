<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\RequestCompiler;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the POST request for a resolved SOAP operation from the step's
 * resolved parameters (plan F4.2).
 *
 * - endpoint: WSDL port location, overridable via step parameters named
 *   `url` or `location`; the SOAP action via a `soapAction` parameter.
 * - SOAP 1.1: Content-Type `text/xml; charset=utf-8` + SOAPAction header.
 * - SOAP 1.2: Content-Type `application/soap+xml; charset=utf-8` (`action=`
 *   parameter) and no SOAPAction header.
 *
 * The override parameters are consumed for the request metadata and are not
 * serialised into the envelope body.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapRequestBuilder
{
    /** @var list<string> */
    private const OVERRIDE_PARAMETERS = ['url', 'location', 'soapAction'];

    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private EvaluationEngineInterface $evaluationEngine,
        private ExpressionEngineInterface $inspector,
    ) {}

    public function build(
        Step $step,
        WorkflowContext $context,
        ArazzoDocument $document,
        ResolvedOperation $resolved,
        string $executionId,
    ): RequestInterface {
        $normalized = $resolved->normalized;
        $bags = $normalized->requestBodies['application/xml'] ?? null;
        if (!is_array($bags)) {
            throw new ExecutionException(
                "SOAP step '{$step->stepId}' has no application/xml request body definition.",
                '/',
                'execution.soap_request_body_missing',
            );
        }

        $soapVersion = is_string($bags['soapVersion'] ?? null) ? $bags['soapVersion'] : '1.1';
        $namespace = is_string($bags['namespace'] ?? null) ? $bags['namespace'] : '';
        if ($namespace === '') {
            throw new ExecutionException(
                "SOAP step '{$step->stepId}' has no request namespace (WSDL targetNamespace missing).",
                '/',
                'execution.soap_namespace_missing',
            );
        }

        $compiled = (new RequestCompiler($this->evaluationEngine, $this->inspector))->compile($step, $document, $context);
        $inputs = $compiled['resolvedInputs'];

        $target = $this->override($inputs, 'url') ?? $this->override($inputs, 'location') ?? $normalized->resolvedServerUrl;
        if ($target === null || $target === '') {
            throw new ExecutionException(
                "SOAP step '{$step->stepId}' has no target endpoint.",
                '/',
                'execution.soap_target_missing',
            );
        }

        $soapAction = $this->override($inputs, 'soapAction') ?? $this->declaredSoapAction($normalized);
        $operationName = $resolved->operationName ?? $normalized->path;
        $bodyParameters = array_diff_key($inputs, array_flip(self::OVERRIDE_PARAMETERS));

        $envelope = SoapEnvelope::build($operationName, $namespace, $bodyParameters, $executionId, $soapVersion);

        $request = $this->requestFactory->createRequest('POST', $target);
        $request = $request->withBody($this->streamFactory->createStream((string) $envelope));

        return $this->applyMediaHeaders($request, $soapVersion, $soapAction);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function override(array $inputs, string $name): ?string
    {
        $value = $inputs[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function declaredSoapAction(NormalizedOpenApiOperation $normalized): ?string
    {
        $soapAction = $normalized->headerParameters['SOAPAction']['value'] ?? null;

        return is_string($soapAction) && $soapAction !== '' ? $soapAction : null;
    }

    private function applyMediaHeaders(RequestInterface $request, string $soapVersion, ?string $soapAction): RequestInterface
    {
        if ($soapVersion === '1.2') {
            $contentType = 'application/soap+xml; charset=utf-8';
            if ($soapAction !== null) {
                $contentType .= '; action="'.str_replace('"', '\\"', $soapAction).'"';
            }

            return $request->withHeader('Content-Type', $contentType);
        }

        $request = $request->withHeader('Content-Type', 'text/xml; charset=utf-8');
        if ($soapAction !== null) {
            $request = $request->withHeader('SOAPAction', $soapAction);
        }

        return $request;
    }
}
