<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Internal;

use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputPart;
use Alama\Arazzo\Expression\Ast\MessageRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\OutputRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepPart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;

/**
 * Resolves the references that read a step record: `$steps.*`, `$outputs.*`,
 * `$response.*`, `$request.*`, `$statusCode`, `$method`, `$url` and
 * `$message.*`.
 *
 * Split out of ExpressionEvaluator so the AST dispatch has one responsibility
 * per evaluator and each class stays under PHPMD's complexity thresholds (#260).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepReferenceResolver
{
    public function __construct(private ValueReader $values) {}

    public function supports(ExpressionAst $ast): bool
    {
        return $ast instanceof StepRef
            || $ast instanceof MessageRef
            || $ast instanceof HttpMetaRef
            || $ast instanceof OutputRef;
    }

    public function resolve(ExpressionAst $ast, EvaluationInputInterface $context): mixed
    {
        return match (true) {
            $ast instanceof StepRef => $this->step($ast, $context),
            $ast instanceof MessageRef => $this->message($ast, $context),
            $ast instanceof HttpMetaRef => $this->httpMeta($ast, $context),
            $ast instanceof OutputRef => $this->output($ast, $context),
            default => null,
        };
    }

    /**
     * The workflow output the current step produced. A workflow-invocation
     * step records the invoked workflow's outputs on the step result, and the
     * step's own `outputs` map is evaluated against that same record.
     */
    private function output(OutputRef $ast, EvaluationInputInterface $context): mixed
    {
        $stepId = $context->getCurrentStepId();
        $stepData = $stepId !== null ? ($context->getWorkflowContext()->getSteps()[$stepId] ?? null) : null;
        $output = is_array($stepData) ? ($this->values->map($stepData, 'outputs')[$ast->name] ?? null) : null;

        return $this->values->withPointer($output, $ast->jsonPointer);
    }

    private function httpMeta(HttpMetaRef $ast, EvaluationInputInterface $context): mixed
    {
        $stepId = $context->getCurrentStepId();
        if ($stepId === null) {
            return null;
        }

        $stepData = $context->getWorkflowContext()->getSteps()[$stepId] ?? null;
        if (!is_array($stepData)) {
            return null;
        }

        $response = $this->values->map($stepData, 'response');
        $request = $this->values->map($stepData, 'request');

        $fields = [
            'statusCode' => $response['statusCode'] ?? null,
            'method' => $request['method'] ?? null,
            'url' => $request['url'] ?? null,
        ];

        return $fields[$ast->field] ?? null;
    }

    private function step(StepRef $ast, EvaluationInputInterface $context): mixed
    {
        $targetStepId = $ast->stepId ?? $context->getCurrentStepId();
        $rawStepData = $context->getWorkflowContext()->getSteps()[$targetStepId] ?? null;
        if (!is_array($rawStepData)) {
            return null;
        }

        /** @var array<string, mixed> $stepData */
        $stepData = $rawStepData;

        return $this->part($ast->part, $stepData);
    }

    /** @param array<string, mixed> $stepData */
    private function part(StepPart $part, array $stepData): mixed
    {
        return match (true) {
            $part instanceof RequestPart => $this->requestPart($part, $stepData),
            $part instanceof ResponsePart => $this->responsePart($part, $stepData),
            $part instanceof OutputPart => $this->values->withPointer(
                $this->values->map($stepData, 'outputs')[$part->name] ?? null,
                $part->jsonPointer,
            ),
            $part instanceof InputPart => $this->values->map($stepData, 'inputs')[$part->name] ?? null,
            default => null,
        };
    }

    /** @param array<string, mixed> $stepData */
    private function requestPart(RequestPart $part, array $stepData): mixed
    {
        $request = is_array($stepData['request'] ?? null) ? $stepData['request'] : [];

        return match ($part->httpPart) {
            'header' => $this->values->map($request, 'headers')[$part->headerName] ?? null,
            'query' => $this->values->map($request, 'query')[$part->headerName] ?? null,
            'path' => $this->values->map($request, 'path')[$part->headerName] ?? null,
            'body' => $this->values->body($request['body'] ?? null, $part->jsonPointer),
            default => null,
        };
    }

    /** @param array<string, mixed> $stepData */
    private function responsePart(ResponsePart $part, array $stepData): mixed
    {
        $response = is_array($stepData['response'] ?? null) ? $stepData['response'] : [];
        if ($part->httpPart === 'statusCode') {
            return $response['statusCode'] ?? null;
        }

        return match ($part->httpPart) {
            'header' => $this->values->map($response, 'headers')[$part->headerName] ?? null,
            'body' => $this->values->body($response['body'] ?? null, $part->jsonPointer),
            default => null,
        };
    }

    private function message(MessageRef $ast, EvaluationInputInterface $context): mixed
    {
        $stepId = $context->getCurrentStepId();
        $stepData = $stepId !== null ? ($context->getWorkflowContext()->getSteps()[$stepId] ?? null) : null;
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

        if ($ast->part === 'header') {
            return $this->header($response, (string) $ast->name);
        }

        $body = is_array($response) ? ($response['body'] ?? []) : [];

        return $this->values->withPointer($body, $ast->jsonPointer);
    }

    private function header(mixed $response, string $name): ?string
    {
        $headers = is_array($response) ? ($response['headers'] ?? []) : [];
        if (!is_array($headers)) {
            return null;
        }

        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0 && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
