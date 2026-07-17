<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Review\ReviewStatus;

final readonly class ReviewRequest
{
    /**
     * @param array<string, mixed> $policyEvidence
     */
    public function __construct(
        public string $uid,
        public ExecutionOperation $operation,
        public string $requestHash,
        public string $userId,
        public string $workspaceId,
        public array $policyEvidence = [],
        public ReviewStatus $status = ReviewStatus::Pending,
        public ?string $decidedBy = null,
        public ?string $decidedAt = null,
        public ?string $decisionNotes = null,
    ) {
    }
}
