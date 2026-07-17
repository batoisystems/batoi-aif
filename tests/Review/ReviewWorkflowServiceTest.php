<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Review;

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Review\InMemoryReviewRepository;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Review\ReviewWorkflowService;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\ReviewRequest;
use PHPUnit\Framework\TestCase;

final class ReviewWorkflowServiceTest extends TestCase
{
    public function testApprovalTransitionIsAuditedWithActorAndWorkspace(): void
    {
        $reviews = new InMemoryReviewRepository();
        $audit = new InMemoryAuditLog();
        $reviews->append(new ReviewRequest(
            uid: 'review_1',
            operation: ExecutionOperation::Tool,
            requestHash: str_repeat('a', 64),
            userId: '10',
            workspaceId: '20',
        ));

        (new ReviewWorkflowService($reviews, $audit))->approve(
            'review_1',
            new ExecutionContext('99', '20', traceUid: 'trace_1'),
            'Verified by finance.',
        );

        self::assertSame(ReviewStatus::Approved, $reviews->get('review_1', '20')?->status);
        self::assertCount(1, $audit->all());
        self::assertSame('review', $audit->all()[0]->operation);
        self::assertSame('99', $audit->all()[0]->userId);
        self::assertSame('20', $audit->all()[0]->workspaceId);
        self::assertSame('approved', $audit->all()[0]->policyDecision['action']);
    }
}
