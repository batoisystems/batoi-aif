<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Value\ReviewRequest;

interface ReviewWorkflowRepositoryInterface extends ReviewRepositoryInterface
{
    public function get(string $uid, string $workspaceId): ?ReviewRequest;

    public function decide(
        string $uid,
        string $workspaceId,
        ReviewStatus $status,
        string $decidedBy,
        ?string $notes = null,
    ): bool;

    public function consumeApproved(string $uid, string $workspaceId, string $requestHash): ?ReviewRequest;
}
