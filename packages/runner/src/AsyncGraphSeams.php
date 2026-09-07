<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Framework-owned ports and config knobs the async execution graph needs.
 * Nullable expression/open-api/transport ports default to runner-built
 * implementations inside the assembler.
 */
final readonly class AsyncGraphSeams
{
    public function __construct(
        public StateStoreInterface $stateStore,
        public QueueDriverInterface $queueDriver,
        public EventLedgerInterface $eventLedger,
        public ExecutionRegistryInterface $executionRegistry,
        public PendingCorrelationRegistryInterface $pendingCorrelationRegistry,
        public DefinitionRegistryInterface $definitionRegistry,
        public LockManagerInterface $lockManager,
        public HttpClientInterface $httpClient,
        public ?OpenApiExecutorInterface $openApiExecutor = null,
        public ?ExpressionResolverInterface $expressionResolver = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?LoggerInterface $logger = null,
        public bool $idempotencyEnabled = false,
        public string $idempotencyHeader = 'Idempotency-Key',
        public bool $strictValidation = false,
        public int $retryCeiling = 10,
        public float $retryBackoffMultiplier = 1.0,
        public int $stateTtlSeconds = 86400,
    ) {}
}
