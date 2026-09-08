<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Exceptions;

use RuntimeException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class GotoTargetNotFoundException extends RuntimeException {}
