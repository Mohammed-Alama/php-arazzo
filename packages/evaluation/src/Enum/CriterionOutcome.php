<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Enum;

/**
 * Whether a single success criterion passed, failed, or could not be evaluated.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum CriterionOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Error = 'error';
}
