<?php

declare(strict_types=1);

namespace Batoi\Aif\Review;

use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Contracts\ReviewWorkflowRepositoryInterface;
use Batoi\Aif\Contracts\MetricsCollectorInterface;
use Batoi\Aif\Exception\AuditPersistenceException;
use Batoi\Aif\Exception\ReviewApprovalException;
use Batoi\Aif\Value\AuditRecord;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\MetricEvent;
use Throwable;

final readonly class ReviewWorkflowService
{
    public function __construct(
        private ReviewWorkflowRepositoryInterface $reviews,
        private AuditLogInterface $auditLog,
        private ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    public function approve(string $reviewUid, ExecutionContext $context, ?string $notes = null): void
    {
        $this->decide($reviewUid, $context, ReviewStatus::Approved, $notes);
    }

    public function reject(string $reviewUid, ExecutionContext $context, ?string $notes = null): void
    {
        $this->decide($reviewUid, $context, ReviewStatus::Rejected, $notes);
    }

    private function decide(
        string $reviewUid,
        ExecutionContext $context,
        ReviewStatus $status,
        ?string $notes,
    ): void {
        $review = $this->reviews->get($reviewUid, $context->workspaceId);
        $decided = $review !== null && $this->reviews->decide(
            $reviewUid,
            $context->workspaceId,
            $status,
            $context->userId,
            $notes,
        );

        if (!$decided) {
            throw new ReviewApprovalException();
        }

        $decision = [
            'action' => $status->value,
            'review_uid' => $reviewUid,
            'notes_supplied' => $notes !== null && $notes !== '',
        ];

        try {
            $this->auditLog->append(new AuditRecord(
                uid: sprintf('audit_%s', bin2hex(random_bytes(8))),
                status: 'ok',
                requestHash: $review->requestHash,
                responseHash: hash('sha256', (string) json_encode($decision, JSON_THROW_ON_ERROR)),
                policyDecision: $decision,
                metadata: ['review_uid' => $reviewUid, 'review_operation' => $review->operation->value],
                userId: $context->userId,
                workspaceId: $context->workspaceId,
                traceUid: $context->traceUid,
                operation: 'review',
                createdAt: gmdate(DATE_ATOM),
            ));
            $this->metrics?->record(new MetricEvent(
                name: 'aif.review.decision',
                tags: ['decision' => $status->value],
                occurredAt: gmdate(DATE_ATOM),
            ));
        } catch (Throwable $exception) {
            throw new AuditPersistenceException($exception);
        }
    }
}
