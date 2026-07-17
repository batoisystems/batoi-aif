<?php

declare(strict_types=1);

namespace Batoi\Aif\Queue;

use Batoi\Aif\Contracts\DurableQueueAdapterInterface;
use Batoi\Aif\Value\QueueJob;
use InvalidArgumentException;

final class InMemoryQueueAdapter implements DurableQueueAdapterInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $jobs = [];

    /** @var array<string, string> */
    private array $idempotencyIndex = [];

    public function dispatch(string $jobName, array $payload, array $options = []): string
    {
        $jobName = trim($jobName);

        if ($jobName === '') {
            throw new InvalidArgumentException('Queue job name is required.');
        }

        $idempotencyKey = $options['idempotency_key'] ?? $payload['idempotency_key'] ?? null;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $indexKey = $jobName . ':' . $idempotencyKey;
            if (isset($this->idempotencyIndex[$indexKey])) {
                return $this->idempotencyIndex[$indexKey];
            }
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
            'attempt' => 0,
            'max_attempts' => max(1, (int) ($options['max_attempts'] ?? 3)),
            'available_at' => microtime(true),
            'lease_owner' => null,
            'lease_expires_at' => null,
        ];

        if (isset($indexKey)) {
            $this->idempotencyIndex[$indexKey] = $jobId;
        }

        return $jobId;
    }

    public function acknowledge(string $jobId): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'acknowledged';
        $this->jobs[$jobId]['lease_owner'] = null;
        $this->jobs[$jobId]['lease_expires_at'] = null;
    }

    public function fail(string $jobId, string $reason, array $metadata = []): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'failed';
        $this->jobs[$jobId]['failure_reason'] = $reason;
        $this->jobs[$jobId]['failure_metadata'] = $metadata;
        $this->jobs[$jobId]['lease_owner'] = null;
        $this->jobs[$jobId]['lease_expires_at'] = null;
    }

    public function reserve(string $workerId, int $leaseSeconds = 60): ?QueueJob
    {
        if (trim($workerId) === '' || $leaseSeconds < 1) {
            throw new InvalidArgumentException('Queue worker ID and a positive lease duration are required.');
        }

        $now = microtime(true);
        foreach ($this->jobs as &$job) {
            if ($job['status'] === 'leased' && (float) $job['lease_expires_at'] <= $now) {
                $job['status'] = 'queued';
                $job['lease_owner'] = null;
                $job['lease_expires_at'] = null;
            }

            if ($job['status'] !== 'queued' || (float) $job['available_at'] > $now) {
                continue;
            }

            if ((int) $job['attempt'] >= (int) $job['max_attempts']) {
                $job['status'] = 'dead_lettered';
                $job['failure_reason'] = 'Maximum attempts exceeded.';
                continue;
            }

            $job['status'] = 'leased';
            $job['attempt'] = (int) $job['attempt'] + 1;
            $job['lease_owner'] = $workerId;
            $job['lease_expires_at'] = $now + $leaseSeconds;

            return $this->toQueueJob($job);
        }
        unset($job);

        return null;
    }

    public function release(string $jobId, int $delaySeconds = 0): void
    {
        $this->requireJob($jobId);
        if ($delaySeconds < 0) {
            throw new InvalidArgumentException('Queue delay cannot be negative.');
        }

        $this->jobs[$jobId]['status'] = 'queued';
        $this->jobs[$jobId]['available_at'] = microtime(true) + $delaySeconds;
        $this->jobs[$jobId]['lease_owner'] = null;
        $this->jobs[$jobId]['lease_expires_at'] = null;
    }

    public function deadLetter(string $jobId, string $reason, array $metadata = []): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'dead_lettered';
        $this->jobs[$jobId]['failure_reason'] = $reason;
        $this->jobs[$jobId]['failure_metadata'] = $metadata;
        $this->jobs[$jobId]['lease_owner'] = null;
        $this->jobs[$jobId]['lease_expires_at'] = null;
    }

    public function cancel(string $jobId): void
    {
        $this->requireJob($jobId);
        $this->jobs[$jobId]['status'] = 'cancelled';
        $this->jobs[$jobId]['lease_owner'] = null;
        $this->jobs[$jobId]['lease_expires_at'] = null;
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

    /** @param array<string, mixed> $job */
    private function toQueueJob(array $job): QueueJob
    {
        return new QueueJob(
            id: (string) $job['id'],
            name: (string) $job['name'],
            payload: is_array($job['payload']) ? $job['payload'] : [],
            options: is_array($job['options']) ? $job['options'] : [],
            attempt: (int) $job['attempt'],
            leaseOwner: is_string($job['lease_owner']) ? $job['lease_owner'] : null,
            leaseExpiresAt: is_float($job['lease_expires_at']) ? $job['lease_expires_at'] : null,
        );
    }
}
