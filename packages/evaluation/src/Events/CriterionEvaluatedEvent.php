<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Events;

use Alama\Arazzo\Evaluation\Data\CriterionResult;

/**
 * Emitted once per evaluated success criterion, short-circuiting at the first
 * non-`Passed` result.
 *
 * The Arazzo spec model has no dedicated document id field, so
 * `documentId` carries `info->version` when a document is supplied and `null`
 * otherwise; the whole document is intentionally not sent.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class CriterionEvaluatedEvent
{
    public function __construct(
        public CriterionResult $result,
        public string $stepId,
        public ?string $documentId = null,
    ) {}
}
