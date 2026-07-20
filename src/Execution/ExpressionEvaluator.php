<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;

class ExpressionEvaluator
{
    public function __construct(private VariableContext $context) {}

    public function evaluate(Expression $expression): mixed
    {
        $ast = $expression->ast();
        return $this->evaluateAst($ast);
    }

    private function evaluateAst(ExpressionAst $ast): mixed
    {
        if ($ast instanceof InputRef) {
            return $this->context->getInputs()[$ast->name] ?? null;
        }

        if ($ast instanceof StepRef) {
            $steps = $this->context->getSteps();
            $stepData = $steps[$ast->stepId] ?? null;
            if (!$stepData) {
                return null;
            }

            $part = $ast->part;
            
            if ($part instanceof RequestPart) {
                $req = $stepData['request'] ?? [];
                $target = match($part->type) {
                    'header' => $req['headers'][$part->name] ?? null,
                    'query' => $req['query'][$part->name] ?? null,
                    'path' => $req['path'][$part->name] ?? null,
                    'body' => JsonPointer::resolve($req['body'] ?? [], $part->jsonPointer),
                    default => null,
                };
                return $target;
            }
            
            if ($part instanceof ResponsePart) {
                $res = $stepData['response'] ?? [];
                if ($part->type === 'statusCode') {
                    return $res['statusCode'] ?? null;
                }
                
                $target = match($part->type) {
                    'header' => $res['headers'][$part->name] ?? null,
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
            $comps = $this->context->getComponents();
            if ($ast->type === 'parameters') {
                return $comps['parameters'][$ast->name] ?? null;
            }
        }

        return null;
    }
}
