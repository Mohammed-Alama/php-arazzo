<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Execution;

use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransfer;

/**
 * Maps an RpcResponseTransfer that carries an RPC error or a transport error
 * into a failed StepExecutionOutcome (plan F5.1).
 *
 * The RPC error keys are copied onto the outcome's responseBody alongside the
 * transfer, so criteria/consumers can inspect `outputs['rpc.error']`-style
 * keys without unwrapping the transfer; the canonical homes stay in the
 * transfer meta.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcFailureMapper
{
    public function map(RpcResponseTransfer $transfer, string $failureCategory = 'rpc-error'): StepExecutionOutcome
    {
        $meta = $transfer->meta();

        $responseBody = ['transfer' => $transfer];
        foreach (['rpc.error', 'rpc.message'] as $key) {
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
