<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use InvalidArgumentException;

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
        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $this->version) !== 1) {
            throw new InvalidArgumentException(sprintf('Prompt version must use semantic versioning: %s', $this->version));
        }

        if (!in_array($this->approvalStatus, ['draft', 'pending_review', 'approved', 'deprecated', 'rejected'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown prompt approval status: %s', $this->approvalStatus));
        }

        if (!in_array($this->riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown prompt risk level: %s', $this->riskLevel));
        }
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus === 'approved';
    }
}
