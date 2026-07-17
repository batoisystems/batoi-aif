<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Queue;

use Batoi\Aif\Queue\InMemoryQueueAdapter;
use PHPUnit\Framework\TestCase;

final class InMemoryQueueAdapterTest extends TestCase
{
    public function testDispatchIsIdempotentAndJobCanBeLeasedAndAcknowledged(): void
    {
        $queue = new InMemoryQueueAdapter();
        $options = ['idempotency_key' => 'same-request', 'max_attempts' => 2];
        $first = $queue->dispatch('aif.embedding.create', ['trace_uid' => 'trace_1'], $options);
        $duplicate = $queue->dispatch('aif.embedding.create', ['trace_uid' => 'trace_1'], $options);

        self::assertSame($first, $duplicate);
        self::assertCount(1, $queue->all());

        $job = $queue->reserve('worker_1');
        self::assertNotNull($job);
        self::assertSame(1, $job->attempt);
        self::assertSame('worker_1', $job->leaseOwner);

        $queue->acknowledge($job->id);
        self::assertSame('acknowledged', $queue->get($job->id)['status']);
    }

    public function testReleasedJobRetriesThenMovesToDeadLetter(): void
    {
        $queue = new InMemoryQueueAdapter();
        $jobId = $queue->dispatch('aif.eval.run', [], ['max_attempts' => 2]);

        self::assertNotNull($queue->reserve('worker_1'));
        $queue->release($jobId);
        self::assertNotNull($queue->reserve('worker_2'));
        $queue->release($jobId);

        self::assertNull($queue->reserve('worker_3'));
        self::assertSame('dead_lettered', $queue->get($jobId)['status']);
    }

    public function testJobCanBeCancelled(): void
    {
        $queue = new InMemoryQueueAdapter();
        $jobId = $queue->dispatch('aif.rag.ingest', []);
        $queue->cancel($jobId);

        self::assertNull($queue->reserve('worker_1'));
        self::assertSame('cancelled', $queue->get($jobId)['status']);
    }
}
