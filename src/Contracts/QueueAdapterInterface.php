<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

interface QueueAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function dispatch(string $jobName, array $payload, array $options = []): string;

    public function acknowledge(string $jobId): void;

    /**
     * @param array<string, mixed> $metadata
     */
    public function fail(string $jobId, string $reason, array $metadata = []): void;
}
