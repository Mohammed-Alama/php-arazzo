<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;

class StringInterpolator
{
    public function __construct(private ExpressionResolverInterface $resolver) {}

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return preg_replace_callback('/\{\$([^\}]+)\}/', function ($matches) use ($context, $stepId) {
            $expr = new Expression('{$'.$matches[1].'}');
            $result = $this->resolver->evaluate($expr, $context, $stepId);
            if ($result === null) {
                return '';
            }

            return is_scalar($result) ? (string) $result : (string) json_encode($result);
        }, $value) ?? $value;
    }
}
