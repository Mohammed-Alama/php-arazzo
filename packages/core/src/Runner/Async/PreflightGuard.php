<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

use Alama\Arazzo\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\PreflightValidator;

/**
 * Runs document preflight validation before the FIRST side effect of a run.
 * Resumed jobs already passed it, so it only fires when no step has
 * recorded a result yet.
 */
final class PreflightGuard
{
    public function __construct(
        private readonly DefinitionRegistryInterface $definitions,
        private readonly ?PreflightValidator $preflight,
    ) {}

    /**
     * @throws PreflightFailureException when validation fails on a fresh run
     */
    public function guard(WorkflowContext $context): void
    {
        if ($this->preflight === null || $context->getSteps() !== []) {
            return; // resumed runs already passed; or gate disabled
        }

        $document = $this->definitions->get($context->getDefinitionId());

        if ($document === null) {
            return; // missing definition is handled downstream as execution.workflow_missing
        }

        $result = $this->preflight->validate($document);

        if (!$result->isValid()) {
            throw new PreflightFailureException(
                'Preflight validation failed with '.count($result->errors).' error(s).',
                $result,
            );
        }
    }
}
