<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;

/**
 * Maps a SoapResponseTransfer that carries a SOAP fault or a transport error
 * into a failed StepExecutionOutcome (plan F4.2).
 *
 * The fault keys are copied onto the outcome's responseBody alongside the
 * transfer, so criteria/consumers can inspect
 * `outputs['soap.faultcode']`-style keys without unwrapping the transfer; the
 * canonical homes stay in the transfer meta (F4.3).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapFaultMapper
{
    public function map(SoapResponseTransfer $transfer, string $failureCategory = 'soap-fault'): StepExecutionOutcome
    {
        $meta = $transfer->meta();

        $responseBody = ['transfer' => $transfer];
        foreach (['soap.faultcode', 'soap.faultstring'] as $key) {
            if (array_key_exists($key, $meta)) {
                $responseBody[$key] = $meta[$key];
            }
        }

        $status = $transfer->status();

        return StepExecutionOutcome::resolved(
            statusCode: is_int($status) ? $status : 500,
            outputs: [],
            responseBody: $responseBody,
            responseHeaders: $transfer->headers(),
            rawBody: is_string($transfer->rawBody()) ? $transfer->rawBody() : null,
            failureCategory: $failureCategory,
        );
    }
}
