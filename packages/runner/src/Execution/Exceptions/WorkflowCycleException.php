<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Exceptions;

use Alama\Arazzo\Contracts\Support\Exceptions\ArazzoException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WorkflowCycleException extends ArazzoException {}
