<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\PayloadReplacer;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\Step;
use Psr\Http\Message\RequestInterface as Psr7Request;
use Psr\Http\Message\ResponseInterface;

/**
 * The Request Compiler's home behind the protocol-executor seams: one
 * implementation of "parameters + payload -> request inputs" and of
 * "response -> canonical record", consumed by every HTTP pipeline
 * (sync StepExecutor and queued HttpStepExecutor) so request shapes
 * cannot drift between adapters.
 */
final class RequestCompiler
{
    public function __construct(private readonly ExpressionValueResolver $values)
    {
    }

    /**
     * Resolves reusable parameters, evaluates every runtime value, applies
     * payload replacements, and routes values into the payload buckets.
     *
     * @return array{payload: OpenApiPayload, resolvedInputs: array<string, mixed>}
     */
    public function compile(Step $step, ArazzoDocument $document, WorkflowContext $context): array
    {
        $payload = new OpenApiPayload();

        $resolvedInputs = [];
        $parameters = new ReusableParameterResolver()->resolve($step->parameters, $document);

        foreach ($parameters as $param) {
            $val = $this->values->resolve($param->value, $context, $step->stepId);

            $resolvedInputs[$param->name] = $val;

            match ($param->in->value ?? 'auto') {
                'query' => $payload->query[$param->name] = $val,
                'header' => $payload->header[$param->name] = $val,
                'path' => $payload->path[$param->name] = $val,
                default => $payload->auto[$param->name] = $val,
            };
        }

        $bodyData = [];

        if ($step->requestBody && $step->requestBody->payload !== null) {
            $bodyData = PayloadReplacer::apply(
                $step,
                is_array($step->requestBody->payload) ? $step->requestBody->payload : [],
                fn (PayloadReplacement $replacement) => $this->values->resolve($replacement->value, $context, $step->stepId),
            );
        }

        $payload->body = $bodyData === [] ? null : $bodyData;

        return ['payload' => $payload, 'resolvedInputs' => $resolvedInputs];
    }

    /**
     * Canonical request-record shape shared by all adapters.
     *
     * @return array<string, mixed>
     */
    public static function requestRecord(?Psr7Request $captured, OpenApiPayload $payload): array
    {
        $queryParams = [];
        parse_str($captured?->getUri()->getQuery() ?? '', $queryParams);

        return [
            'method' => $captured?->getMethod(),
            'url' => $captured !== null ? (string) $captured->getUri() : '',
            'query' => $queryParams,
            'path' => $payload->path,
            'headers' => self::flattenHeaders($captured?->getHeaders() ?? []),
            'body' => is_array($payload->body) ? $payload->body : [],
        ];
    }

    /**
     * Canonical response decode shared by all adapters.
     *
     * @return array{statusCode: int, headers: array<string, string>, body: array<string, mixed>, rawBody: string, contentType: string}
     */
    public static function decodeResponse(ResponseInterface $response): array
    {
        $rawBody = (string) $response->getBody();
        $decoded = json_decode($rawBody, true);
        $body = is_array($decoded) ? $decoded : [];

        /** @var array<string, mixed> $body */
        return [
            'statusCode' => $response->getStatusCode(),
            'headers' => self::flattenHeaders($response->getHeaders()),
            'body' => $body,
            'rawBody' => $rawBody,
            'contentType' => $response->getHeaderLine('Content-Type'),
        ];
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<string, string>
     */
    public static function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $name => $values) {
            if (!is_string($name) || !is_array($values)) {
                continue;
            }

            $flat[$name] = implode(', ', array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $values,
            ));
        }

        return $flat;
    }
}
