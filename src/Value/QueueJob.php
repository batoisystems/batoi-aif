<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class QueueJob
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $payload,
        public array $options,
        public int $attempt,
        public ?string $leaseOwner,
        public ?float $leaseExpiresAt,
    ) {
    }
}
