<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Psr\Http\Message\RequestInterface;

final class IdempotencyKeyInjector
{
    private const MUTATING_METHODS = ['POST', 'PATCH', 'DELETE'];

    public function __construct(
        private bool $enabledDefault,
        private string $headerDefault,
    ) {
    }

    public function inject(RequestInterface $request, Step $step, WorkflowContext $context): InjectionResult
    {
        $enabled = $step->idempotencyKey ?? $this->enabledDefault;
        if (!$enabled) {
            return new InjectionResult($request);
        }

        if (!in_array(strtoupper($request->getMethod()), self::MUTATING_METHODS, true)) {
            return new InjectionResult($request);
        }

        // Task 5 lands key computation here. For now, still a no-op so the enable/method tests pass.
        return new InjectionResult($request);
    }
}
