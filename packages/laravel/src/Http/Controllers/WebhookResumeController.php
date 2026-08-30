<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Http\Controllers;

use Alama\Arazzo\Async\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Jobs\ResumeCorrelationJob;
use Alama\Arazzo\State\Interfaces\PendingCorrelationRegistryInterface;
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
