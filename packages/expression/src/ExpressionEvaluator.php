<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputPart;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\MessageRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\SelfRef;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        $ast = new ExpressionParser()->parse($expression->raw);

        return $this->evaluateAst($ast, $context);
    }

    private function evaluateAst(ExpressionAst $ast, EvaluationInputInterface $context): mixed
    {
        if ($ast instanceof InputRef) {
            $value = $context->getWorkflowContext()->getInputs()[$ast->name] ?? null;

            return $ast->jsonPointer !== null && (is_array($value) || $value === null)
                ? JsonPointer::resolve(is_array($value) ? $value : [], $ast->jsonPointer)
                : $value;
        }

        if ($ast instanceof HttpMetaRef) {
            if ($context->getCurrentStepId() === null) {
                return null;
            }

            $stepData = $context->getWorkflowContext()->getSteps()[$context->getCurrentStepId()] ?? null;
            if (!$stepData) {
                return null;
            }

            return match ($ast->field) {
                'statusCode' => $stepData['response']['statusCode'] ?? null,
                'method' => $stepData['request']['method'] ?? null,
                'url' => $stepData['request']['url'] ?? null,
            };
        }

        if ($ast instanceof StepRef) {
            $steps = $context->getWorkflowContext()->getSteps();
            $targetStepId = $ast->stepId ?? $context->getCurrentStepId();
            $rawStepData = $steps[$targetStepId] ?? null;
            if (!is_array($rawStepData)) {
                return null;
            }

            /** @var array<string, mixed> $stepData */
            $stepData = $rawStepData;

            $part = $ast->part;

            if ($part instanceof RequestPart) {
                $req = is_array($stepData['request'] ?? null) ? $stepData['request'] : [];

                return match ($part->httpPart) {
                    'header' => $this->mapOrEmpty($req, 'headers')[$part->headerName] ?? null,
                    'query' => $this->mapOrEmpty($req, 'query')[$part->headerName] ?? null,
                    'path' => $this->mapOrEmpty($req, 'path')[$part->headerName] ?? null,
                    'body' => JsonPointer::resolve(is_array($req['body'] ?? null) ? $req['body'] : [], $part->jsonPointer),
                    default => null,
                };
            }

            if ($part instanceof ResponsePart) {
                $res = is_array($stepData['response'] ?? null) ? $stepData['response'] : [];
                if ($part->httpPart === 'statusCode') {
                    return $res['statusCode'] ?? null;
                }

                return match ($part->httpPart) {
                    'header' => $this->mapOrEmpty($res, 'headers')[$part->headerName] ?? null,
                    'body' => JsonPointer::resolve(is_array($res['body'] ?? null) ? $res['body'] : [], $part->jsonPointer),
                    default => null,
                };
            }

            if ($part instanceof OutputPart) {
                $output = $this->mapOrEmpty($stepData, 'outputs')[$part->name] ?? null;

                return $part->jsonPointer !== null && (is_array($output) || $output === null)
                    ? JsonPointer::resolve(is_array($output) ? $output : [], $part->jsonPointer)
                    : $output;
            }

            if ($part instanceof InputPart) {
                return $this->mapOrEmpty($stepData, 'inputs')[$part->name] ?? null;
            }
        }

        if ($ast instanceof WorkflowRef) {
            $workflowData = $context->getWorkflowContext()->getWorkflows()[$ast->workflowId] ?? null;
            if ($workflowData === null) {
                return null;
            }

            return $workflowData[$ast->partKind][$ast->name] ?? null;
        }

        if ($ast instanceof MessageRef) {
            $steps = $context->getWorkflowContext()->getSteps();
            $stepData = $context->getCurrentStepId() !== null ? ($steps[$context->getCurrentStepId()] ?? null) : null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

            if ($ast->part === 'header') {
                $headers = is_array($response) ? ($response['headers'] ?? []) : [];
                if (!is_array($headers)) {
                    return null;
                }

                foreach ($headers as $key => $value) {
                    if (is_string($key) && strcasecmp($key, (string) $ast->name) === 0 && is_scalar($value)) {
                        return (string) $value;
                    }
                }

                return null;
            }

            // payload
            $body = is_array($response) ? ($response['body'] ?? []) : [];

            return $ast->jsonPointer !== null
                ? JsonPointer::resolve(is_array($body) ? $body : [], $ast->jsonPointer)
                : $body;
        }

        if ($ast instanceof SelfRef) {
            return $context->getDocument()?->self;
        }

        if ($ast instanceof ComponentRef) {
            $comps = $context->getWorkflowContext()->getComponents();
            if ($ast->type === 'parameters') {
                return $comps['parameters'][$ast->name] ?? null;
            }
        }

        if ($ast instanceof SourceRef && $context->getDocument()) {
            foreach ($context->getDocument()->sourceDescriptions as $sourceDesc) {
                if ($sourceDesc->name === $ast->name) {
                    if ($ast->subPath === 'url') {
                        return $sourceDesc->url;
                    }
                    if ($ast->subPath === 'type') {
                        return $sourceDesc->type->value;
                    }
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function mapOrEmpty(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
