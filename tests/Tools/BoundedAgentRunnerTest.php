<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Tools;

use Batoi\Aif\Agents\BoundedAgentRunner;
use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Exception\AgentBudgetExceededException;
use Batoi\Aif\Exception\RequestValidationException;
use Batoi\Aif\Exception\ReviewRequiredException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Rad\RadRolePermissionChecker;
use Batoi\Aif\Review\InMemoryReviewRepository;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Tests\Fixtures\RecordingTool;
use Batoi\Aif\Tools\ToolRegistry;
use Batoi\Aif\Tools\ToolSideEffect;
use Batoi\Aif\Value\AgentBudget;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\ToolCall;
use Batoi\Aif\Value\ToolDefinition;
use PHPUnit\Framework\TestCase;

final class BoundedAgentRunnerTest extends TestCase
{
    private ExecutionContext $context;

    protected function setUp(): void
    {
        $this->context = new ExecutionContext('10', '20', ['admin']);
    }

    public function testBoundedRunnerExecutesPermissionCheckedAuditedSteps(): void
    {
        $audit = new InMemoryAuditLog();
        $tool = new RecordingTool(new ToolDefinition(
            code: 'ticket.update',
            sideEffect: ToolSideEffect::Write,
            permission: 'ticket.update',
            idempotent: false,
            requiredArguments: ['ticket_uid'],
        ));
        $gateway = $this->gateway(
            $audit,
            permissionChecker: new RadRolePermissionChecker(['ticket.update' => ['admin']]),
        );
        $runner = new BoundedAgentRunner($gateway, new ToolRegistry([$tool]));
        $calls = [
            new ToolCall('ticket.update', ['ticket_uid' => 'ticket_1', 'idempotency_key' => 'idem_1']),
            new ToolCall('ticket.update', ['ticket_uid' => 'ticket_2', 'idempotency_key' => 'idem_2']),
        ];

        $result = $runner->run($calls, $this->context, new AgentBudget(maxSteps: 2));

        self::assertCount(2, $result->steps);
        self::assertCount(2, $tool->calls);
        self::assertCount(2, $audit->all());
        self::assertSame('tool', $audit->all()[0]->operation);
    }

    public function testReviewRequiredToolPausesBeforeSideEffect(): void
    {
        $audit = new InMemoryAuditLog();
        $reviews = new InMemoryReviewRepository();
        $tool = new RecordingTool(new ToolDefinition(
            code: 'payment.send',
            sideEffect: ToolSideEffect::External,
            requiresReview: true,
        ));
        $gateway = $this->gateway($audit, $reviews);

        try {
            $gateway->executeTool($tool, [], $this->context);
            self::fail('Review-required tool should pause.');
        } catch (ReviewRequiredException) {
            self::assertSame([], $tool->calls);
            self::assertCount(1, $reviews->all());
            self::assertSame('review_required', $audit->all()[0]->status);
        }
    }

    public function testApprovedToolReviewExecutesOnceAndRejectsReplay(): void
    {
        $reviews = new InMemoryReviewRepository();
        $tool = new RecordingTool(new ToolDefinition(
            code: 'payment.send',
            sideEffect: ToolSideEffect::External,
            requiresReview: true,
        ));
        $gateway = $this->gateway(new InMemoryAuditLog(), $reviews);

        try {
            $gateway->executeTool($tool, ['amount' => 100], $this->context);
            self::fail('Review-required tool should pause.');
        } catch (ReviewRequiredException $exception) {
            $reviewUid = $exception->reviewUid;
        }

        self::assertTrue($reviews->decide($reviewUid, '20', ReviewStatus::Approved, '99'));
        $gateway->executeTool($tool, ['amount' => 100], $this->context, $reviewUid);
        self::assertCount(1, $tool->calls);
        self::assertSame(ReviewStatus::Consumed, $reviews->get($reviewUid, '20')?->status);

        $this->expectException(\Batoi\Aif\Exception\ReviewApprovalException::class);
        $gateway->executeTool($tool, ['amount' => 100], $this->context, $reviewUid);
    }

    public function testApprovedToolReviewCannotAuthorizeChangedArguments(): void
    {
        $reviews = new InMemoryReviewRepository();
        $tool = new RecordingTool(new ToolDefinition('payment.send', requiresReview: true));
        $gateway = $this->gateway(new InMemoryAuditLog(), $reviews);

        try {
            $gateway->executeTool($tool, ['amount' => 100], $this->context);
            self::fail('Review-required tool should pause.');
        } catch (ReviewRequiredException $exception) {
            $reviewUid = $exception->reviewUid;
        }

        self::assertTrue($reviews->decide($reviewUid, '20', ReviewStatus::Approved, '99'));
        $this->expectException(\Batoi\Aif\Exception\ReviewApprovalException::class);
        $gateway->executeTool($tool, ['amount' => 101], $this->context, $reviewUid);
    }

    public function testNonIdempotentToolRequiresKey(): void
    {
        $tool = new RecordingTool(new ToolDefinition(
            code: 'external.send',
            sideEffect: ToolSideEffect::External,
            idempotent: false,
        ));

        $this->expectException(RequestValidationException::class);
        $this->gateway(new InMemoryAuditLog())->executeTool($tool, [], $this->context);
    }

    public function testStepBudgetIsCheckedBeforeExecution(): void
    {
        $tool = new RecordingTool(new ToolDefinition('read'));
        $runner = new BoundedAgentRunner(
            $this->gateway(new InMemoryAuditLog()),
            new ToolRegistry([$tool]),
        );

        try {
            $runner->run(
                [new ToolCall('read'), new ToolCall('read')],
                $this->context,
                new AgentBudget(maxSteps: 1),
            );
            self::fail('Step budget should fail before execution.');
        } catch (AgentBudgetExceededException) {
            self::assertSame([], $tool->calls);
        }
    }

    private function gateway(
        InMemoryAuditLog $audit,
        ?InMemoryReviewRepository $reviews = null,
        ?RadRolePermissionChecker $permissionChecker = null,
    ): AifGateway {
        return new AifGateway(
            providers: new InMemoryProviderRegistry(['mock' => new MockProvider()]),
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
            reviewRepository: $reviews,
            permissionChecker: $permissionChecker,
        );
    }
}
