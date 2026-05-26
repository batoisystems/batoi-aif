<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class PromptVersion
{
    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $code,
        public string $version,
        public string $template,
        public string $approvalStatus = 'approved',
        public string $riskLevel = 'low',
        public array $inputSchema = [],
        public array $metadata = [],
    ) {
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus === 'approved';
    }
}
