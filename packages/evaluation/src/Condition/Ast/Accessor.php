<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition\Ast;

/**
 * A property de-reference (`.name`) or index access (`[n]`) applied to the
 * value of a runtime expression in a simple condition.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface Accessor {}
