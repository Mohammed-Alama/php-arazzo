<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\ResolvedOperation;

/**
 * Document-side seam for operation resolution during preflight and through
 * the document facade. Implemented by the HTTP/OpenAPI protocol package
 * (alama/arazzo-protocol-http) so nothing below the runner ever names a
 * protocol type.
 *
 * Deliberately exposes only `resolveModel()`, the cebe-free projection. The
 * concrete resolver's `resolve()` additionally returns `OpenApiOperationHandle`,
 * which carries cebe objects and therefore lives above this layer.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OpenApiOperationResolverInterface
{
    public function resolveModel(Step $step, ArazzoDocument $document): ResolvedOperation;
}
