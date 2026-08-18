# Mutating Updates & Outbox

Category: **exec** · Phase: **2-orchestration** · Tier: **OSS**
Depends on: shipped `exec-47-signals-and-queries`

## Problem

`exec-47-signals-and-queries` introduces basic signals (pushing data in to advance execution) and queries (reading data out without affecting execution). However, complex orchestrations frequently require two additional communication primitives that `durable-workflow` natively provides:

1. **Mutating Updates**: A combined signal and query. An external actor needs to synchronously push a payload that mutates the workflow's internal state (e.g., adding an item to a cart or modifying a configuration) and immediately receive a confirmation or the newly updated state, all without necessarily advancing the workflow to the next step.
2. **Outbox (Replay-Safe Outgoing Messages)**: When a workflow needs to send outgoing messages (like firing a webhook, emitting a domain event, or logging to an external system), doing this directly in a step or side-effect risks double-firing during replays. 

Since Arazzo workflows are declarative, we need extensions to handle these without breaking determinism or state immutability.

## Feature

### 1. Mutating Updates (`UpdateHandlerInterface`)

```php
interface UpdateHandlerInterface
{
    public function update(string $executionId, string $updateName, array $payload): mixed;
}
```

- Introduce an `x-on-update` block (at the workflow or step level) to define declarative state mutations.
- When an update is received, the engine evaluates a defined JSONPath/expression to mutate the `$context` or a dedicated `$updates` state variable.
- The engine appends an `UpdateReceived` event to the `EventLedgerInterface`. Replay deterministically applies this mutation.
- The method synchronously returns a defined output expression (acting as the query half of the update).

```yaml
x-on-update:
  - name: addItem
    # Mutate state durably
    mutate:
      - target: $context.cartItems
        action: append
        value: $payload.item
    # Synchronous return value for the caller
    return: $context.cartItems
```

### 2. Outbox Semantics (`OutboxInterface`)

```php
interface OutboxInterface
{
    public function send(string $executionId, string $messageId, array $payload): void;
    public function nextUnsent(string $executionId): ?array;
}
```

- A step extension `x-outbox-send` that allows the workflow to safely dispatch messages.
- The Outbox intercepts outgoing messages. It checks the `EventLedgerInterface` for an `OutboxMessageSent` event with the corresponding `$messageId`.
- If the event exists (i.e. we are replaying after a crash), the message is dropped/ignored, ensuring exactly-once delivery.
- If the event does not exist, the message is dispatched and the `OutboxMessageSent` event is persisted.

## Acceptance

- **Updates**: A caller can hit the `UpdateHandlerInterface::update()` method and receive a synchronously computed return value based on the mutated state. 
- **Update Replay**: Replaying an execution that received 5 updates correctly reconstructs the `$context` state without double-applying or dropping any mutations.
- **Outbox**: An `x-outbox-send` step inside an `x-loop` (from `core-50`) that fails halfway through will NOT re-send the messages from the first half of the loop when the worker restarts and replays the execution.

## Out of scope

- Direct integration with asynchronous message brokers (Kafka/RabbitMQ) for the outbox. The `OutboxInterface` handles the exactly-once deduplication logic internally; the actual transport mechanism is deferred to the application's event dispatcher.
