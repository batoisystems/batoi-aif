<?php

declare(strict_types=1);

namespace Batoi\Aif\Review;

use Batoi\Aif\Contracts\ReviewWorkflowRepositoryInterface;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Value\ReviewRequest;

final class InMemoryReviewRepository implements ReviewWorkflowRepositoryInterface
{
    /** @var array<string, ReviewRequest> */
    private array $reviews = [];

    public function append(ReviewRequest $review): void
    {
        $this->reviews[$review->uid] = $review;
    }

    /** @return list<ReviewRequest> */
    public function all(): array
    {
        return array_values($this->reviews);
    }

    public function get(string $uid, string $workspaceId): ?ReviewRequest
    {
        $review = $this->reviews[$uid] ?? null;

        return $review?->workspaceId === $workspaceId ? $review : null;
    }

    public function decide(
        string $uid,
        string $workspaceId,
        ReviewStatus $status,
        string $decidedBy,
        ?string $notes = null,
    ): bool {
        $review = $this->get($uid, $workspaceId);
        if ($review === null || $review->status !== ReviewStatus::Pending) {
            return false;
        }

        if (!in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            return false;
        }

        $this->reviews[$uid] = $this->copy($review, $status, $decidedBy, $notes);

        return true;
    }

    public function consumeApproved(string $uid, string $workspaceId, string $requestHash): ?ReviewRequest
    {
        $review = $this->get($uid, $workspaceId);
        if ($review === null || $review->status !== ReviewStatus::Approved || !hash_equals($review->requestHash, $requestHash)) {
            return null;
        }

        $this->reviews[$uid] = $this->copy(
            $review,
            ReviewStatus::Consumed,
            $review->decidedBy,
            $review->decisionNotes,
            $review->decidedAt,
        );

        return $this->reviews[$uid];
    }

    private function copy(
        ReviewRequest $review,
        ReviewStatus $status,
        ?string $decidedBy,
        ?string $notes,
        ?string $decidedAt = null,
    ): ReviewRequest {
        return new ReviewRequest(
            uid: $review->uid,
            operation: $review->operation,
            requestHash: $review->requestHash,
            userId: $review->userId,
            workspaceId: $review->workspaceId,
            policyEvidence: $review->policyEvidence,
            status: $status,
            decidedBy: $decidedBy,
            decidedAt: $decidedAt ?? gmdate(DATE_ATOM),
            decisionNotes: $notes,
        );
    }
}
