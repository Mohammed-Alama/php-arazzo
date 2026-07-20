<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Laravel\Http\Controllers;

use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

uses(TestCase::class);

class WebhookControllerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

it('returns 404 and dispatches nothing when the correlation is unknown', function (): void {
    Queue::fake();
    $this->app->instance(PendingCorrelationRegistryInterface::class, new WebhookControllerMockPendingCorrelations());

    postJson('/api/arazzo/webhooks/unknown-corr', ['rideId' => 'r_1'])
        ->assertStatus(404);

    Queue::assertNothingPushed();
});

it('returns 202 and dispatches a ResumeCorrelationJob when the correlation is found', function (): void {
    Queue::fake();
    $fake = new WebhookControllerMockPendingCorrelations();
    $fake->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $this->app->instance(PendingCorrelationRegistryInterface::class, $fake);

    postJson('/api/arazzo/webhooks/corr_1', ['rideId' => 'r_1'])
        ->assertStatus(202);

    Queue::assertPushed(RunResumeCorrelationJob::class, function (RunResumeCorrelationJob $pushed) {
        return $pushed->inner->correlationId === 'corr_1' && $pushed->inner->response === ['rideId' => 'r_1'];
    });
});
