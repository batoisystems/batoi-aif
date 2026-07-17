<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\QueueJob;

interface DurableQueueAdapterInterface extends QueueAdapterInterface
{
    public function reserve(string $workerId, int $leaseSeconds = 60): ?QueueJob;

    public function release(string $jobId, int $delaySeconds = 0): void;

    /** @param array<string, mixed> $metadata */
    public function deadLetter(string $jobId, string $reason, array $metadata = []): void;

    public function cancel(string $jobId): void;
}
