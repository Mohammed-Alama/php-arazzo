<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;

class ExpressionEvaluator
{
    public function evaluate(Expression $expression, VariableContext $context): mixed
    {
        $ast = $expression->ast();

        return $this->evaluateAst($ast, $context);
    }

    private function evaluateAst(ExpressionAst $ast, VariableContext $context): mixed
    {
        if ($ast instanceof InputRef) {
            return $context->getInputs()[$ast->name] ?? null;
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
