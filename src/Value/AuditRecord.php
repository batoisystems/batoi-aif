<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class AuditRecord
{
    /**
     * @param array<string, mixed> $policyDecision
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $uid,
        public string $status,
        public string $requestHash,
        public ?string $responseHash = null,
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $promptCode = null,
        public ?string $promptVersion = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $policyDecision = [],
        public array $usage = [],
        public array $metadata = [],
    ) {
    }
}
