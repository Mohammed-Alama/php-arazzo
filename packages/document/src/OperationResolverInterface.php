<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Protocol-agnostic SPI that turns a Step into the ResolvedOperation a
 * protocol executor consumes (plan F5.1 ruling: an operation resolver is a
 * generic runtime seam, so no protocol name belongs in the interface).
 *
 * Implemented per protocol source — WSDL/SOAP (WsdlOperationResolver),
 * Protobuf/RPC (ProtobufOperationResolver), OpenAPI/HTTP and future CLI
 * resolvers — so a host can push any of them through one type.
 *
 * supportsStep() is the side-effect-free source grammar (it never hits the
 * wire); resolve() additionally fetches and normalises the described source,
 * so it may perform I/O. Lives in document (next to ResolvedOperation itself)
 * because the return type is a document type: contracts cannot reference it
 * without inverting the contracts <- document dependency.
 */
interface OperationResolverInterface
{
    public function supportsStep(Step $step, ArazzoDocument $document): bool;

    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation;
}
