<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\ResolvedOperation;

/**
 * Resolves a Step targeting a WSDL-described SOAP operation into the
 * ResolvedOperation the SOAP pipeline consumes (plan F4.2 seam).
 *
 * supportsStep() is the side-effect-free source grammar (the same
 * OpenApiOperationResolver rules), resolve() additionally fetches and
 * normalises the WSDL, so it may hit the wire.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface SoapOperationResolverInterface
{
    public function supportsStep(Step $step, ArazzoDocument $document): bool;

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation;
}
