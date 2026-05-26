# Queue Adapters

Queue adapters let AIF run retryable and long-running work without coupling core to a queue backend.

## Contract

```php
use Batoi\Aif\Contracts\QueueAdapterInterface;
```

Required methods:

- `dispatch(string $jobName, array $payload, array $options = []): string`
- `acknowledge(string $jobId): void`
- `fail(string $jobId, string $reason, array $metadata = []): void`

## Baseline Adapter

```php
use Batoi\Aif\Queue\InMemoryQueueAdapter;

$queue = new InMemoryQueueAdapter();
$jobId = $queue->dispatch('aif.embedding.create', [
    'request_uid' => 'req_123',
    'idempotency_key' => 'embed_req_123',
]);
```

## Job Names

Recommended initial names:

- `aif.audit.persist`
- `aif.embedding.create`
- `aif.rag.ingest`
- `aif.eval.run`
- `aif.agent.step`
- `aif.review.notify`

## Payload Rules

Queued payloads should include:

- request UID
- trace UID
- workspace or tenant ID when available
- actor/user ID when available
- policy decision snapshot or policy recheck flag
- prompt version snapshot when applicable
- idempotency key

Queued payloads must not include provider API keys, access tokens, or raw secrets. Load secrets at execution time from the host application's secret/config system.

## Execution Rules

- High-risk jobs should re-run policy before execution.
- Provider calls in queued jobs must still pass through `AifGateway`.
- Failed jobs should produce audit evidence.
- Duplicate jobs should be safe through request UID or idempotency key.
