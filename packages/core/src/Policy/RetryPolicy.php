<?php

declare(strict_types=1);

namespace Alama\Arazzo\Policy;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Contracts\BackoffCalculatorInterface;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Step;

final class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 10,
        public float $backoffMultiplier = 1.0,
        public ?BackoffCalculatorInterface $calculator = null,
    ) {
        $this->calculator ??= new ExponentialBackoffCalculator();
    }

    public function calculateDelay(RetryAction $action, Step $step, WorkflowContext $context, int $upcomingAttempt): int
    {
        $headerValue = self::lookupHeader($context, $step->stepId, 'Retry-After');

        if ($headerValue !== null) {
            if (preg_match('/^\d+$/', trim($headerValue)) === 1) {
                return max(0, (int) trim($headerValue));
            }

            $date = \DateTimeImmutable::createFromFormat(\DATE_RFC7231, trim($headerValue));
            if ($date !== false) {
                return max(0, $date->getTimestamp() - time());
            }
        }

        $base = $action->retryAfter ?? 0;
        $calculator = $this->calculator ?? new ExponentialBackoffCalculator();

        return $calculator->calculate($base, $upcomingAttempt, $this->backoffMultiplier);
    }

    public function isExhausted(int $attemptsSoFar, ?int $limit): bool
    {
        $effectiveLimit = $limit ?? PHP_INT_MAX;
        $effectiveLimit = min($effectiveLimit, $this->maxAttempts);

        return $attemptsSoFar >= $effectiveLimit;
    }

    private static function lookupHeader(WorkflowContext $context, string $stepId, string $name): ?string
    {
        $steps = $context->getSteps();
        $stepData = $steps[$stepId] ?? null;
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
        if (!is_array($response)) {
            return null;
        }

        $headers = $response['headers'] ?? [];
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
