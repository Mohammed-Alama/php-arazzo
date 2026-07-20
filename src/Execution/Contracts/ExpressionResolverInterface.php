<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context): RequestInterface;
    public function extractOutputs(Step $step, array $responseData): array;
}
