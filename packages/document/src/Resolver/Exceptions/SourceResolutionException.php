<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver\Exceptions;

use RuntimeException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
abstract class SourceResolutionException extends RuntimeException {}
