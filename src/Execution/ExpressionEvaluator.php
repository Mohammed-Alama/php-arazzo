<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;

class ExpressionEvaluator
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        $ast = $expression->ast();

        return $this->evaluateAst($ast, $context, $currentStepId);
    }

    private function evaluateAst(ExpressionAst $ast, WorkflowContext $context, ?string $currentStepId): mixed
    {
        if ($ast instanceof InputRef) {
            return $context->getInputs()[$ast->name] ?? null;
        }

        if ($ast instanceof HttpMetaRef) {
            if ($currentStepId === null) {
                return null;
            }

            $stepData = $context->getSteps()[$currentStepId] ?? null;
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
            $steps = $context->getSteps();
            $stepData = $steps[$ast->stepId] ?? null;
            if (!$stepData) {
                return null;
            }

            $part = $ast->part;

            if ($part instanceof RequestPart) {
                $req = $stepData['request'] ?? [];
                $target = match ($part->httpPart) {
                    'header' => $req['headers'][$part->headerName] ?? null,
                    'body' => JsonPointer::resolve($req['body'] ?? [], $part->jsonPointer),
                    default => null,
                };

                return $target;
            }

            if ($part instanceof ResponsePart) {
                $res = $stepData['response'] ?? [];
                if ($part->httpPart === 'statusCode') {
                    return $res['statusCode'] ?? null;
                }

                $target = match ($part->httpPart) {
                    'header' => $res['headers'][$part->headerName] ?? null,
                    'body' => JsonPointer::resolve($res['body'] ?? [], $part->jsonPointer),
                    default => null,
                };

                return $target;
            }

            if ($part instanceof OutputPart) {
                return $stepData['outputs'][$part->name] ?? null;
            }
        }

        if ($ast instanceof ComponentRef) {
            $comps = $context->getComponents();
            if ($ast->type === 'parameters') {
                return $comps['parameters'][$ast->name] ?? null;
            }
        }

        return null;
    }
}
