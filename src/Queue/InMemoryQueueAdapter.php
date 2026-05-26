<?php

declare(strict_types=1);

namespace Batoi\Aif\Queue;

use Batoi\Aif\Contracts\QueueAdapterInterface;
use InvalidArgumentException;

final class InMemoryQueueAdapter implements QueueAdapterInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $jobs = [];

    public function dispatch(string $jobName, array $payload, array $options = []): string
    {
        $jobName = trim($jobName);

        if ($jobName === '') {
            throw new InvalidArgumentException('Queue job name is required.');
        }

        $jobId = $this->newJobId();
        $this->jobs[$jobId] = [
            'id' => $jobId,
            'name' => $jobName,
            'payload' => $payload,
            'options' => $options,
            'status' => 'queued',
            'failure_reason' => null,
            'failure_metadata' => [],
        ];

        return $jobId;
    }

    public function acknowledge(string $jobId): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'acknowledged';
    }

    public function fail(string $jobId, string $reason, array $metadata = []): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'failed';
        $this->jobs[$jobId]['failure_reason'] = $reason;
        $this->jobs[$jobId]['failure_metadata'] = $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $jobId): array
    {
        $this->requireJob($jobId);

        return $this->jobs[$jobId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->jobs);
    }

    private function requireJob(string $jobId): void
    {
        if (!isset($this->jobs[$jobId])) {
            throw new InvalidArgumentException(sprintf('Queue job not found: %s', $jobId));
        }
    }

    private function newJobId(): string
    {
        return sprintf('job_%s', bin2hex(random_bytes(8)));
    }
}
