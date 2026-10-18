<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Http\Controllers;

use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Laravel\Support\Payload;
use Alama\Arazzo\Runner\Async\ResumeCorrelationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class WebhookResumeController extends Controller
{
    public function resume(
        string $correlationId,
        Request $request,
        PendingCorrelationRegistryInterface $pendingCorrelations,
        QueueDriverInterface $queueDriver,
    ): JsonResponse {
        $correlation = $pendingCorrelations->findByCorrelationId($correlationId);
        if ($correlation === null) {
            return response()->json(['error' => 'correlation not found'], 404);
        }

        $payload = Payload::record($request->json()->all());
        $queueDriver->dispatch(new ResumeCorrelationJob($correlationId, $payload));

        return response()->json([], 202);
    }
}
