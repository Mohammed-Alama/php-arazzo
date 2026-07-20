<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Http\Controllers;

use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\ResumeCorrelationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        $payload = (array) $request->json()->all();
        $queueDriver->dispatch(new ResumeCorrelationJob($correlationId, $payload));

        return response()->json([], 202);
    }
}
