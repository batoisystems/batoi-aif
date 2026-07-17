<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Gateway;

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Exception\GovernanceConfigurationException;
use Batoi\Aif\Exception\EvaluationBlockedException;
use Batoi\Aif\Exception\AuditPersistenceException;
use Batoi\Aif\Evaluation\EvaluationPipeline;
use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Exception\ProviderCapabilityException;
use Batoi\Aif\Exception\ReviewRequiredException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Policy\PolicyAction;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Observability\InMemoryMetricsCollector;
use Batoi\Aif\Prompts\InMemoryPromptRegistry;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Review\InMemoryReviewRepository;
use Batoi\Aif\Tests\Fixtures\ConfigurablePolicyEngine;
use Batoi\Aif\Tests\Fixtures\FailingPostEvaluator;
use Batoi\Aif\Tests\Fixtures\FailingAuditLog;
use Batoi\Aif\Tests\Fixtures\RecordingProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\PromptVersion;
use PHPUnit\Framework\TestCase;

final class AifGatewayGovernanceTest extends TestCase
{
    private RecordingProvider $provider;

    private InMemoryProviderRegistry $providers;

    private ExecutionContext $context;

    protected function setUp(): void
    {
        $this->provider = new RecordingProvider();
        $this->providers = new InMemoryProviderRegistry(['recording' => $this->provider]);
        $this->context = new ExecutionContext('user_1', 'space_1', ['admin'], 'trace_1');
    }

    public function testGovernedModeFailsClosedWithoutContextAndAuditsOnce(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = $this->gateway(new StaticPolicyEngine(), $audit);

        try {
            $gateway->infer(new InferenceRequest('Must not execute'));
            self::fail('Governed execution without context should fail.');
        } catch (GovernanceConfigurationException) {
            self::assertSame([], $this->provider->calls);
            self::assertCount(1, $audit->all());
            self::assertSame('error', $audit->all()[0]->status);
        }
    }

    public function testPromptPreparationFailureIsAuditedBeforeProviderAccess(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = $this->gateway(
            new StaticPolicyEngine(),
            $audit,
            new InMemoryPromptRegistry([
                new PromptVersion('needs_value', '1.0.0', 'Value: {{value}}'),
            ]),
        );

        try {
            $gateway->infer(new InferenceRequest('', promptCode: 'needs_value'), $this->context);
            self::fail('Missing prompt input should fail.');
        } catch (PromptRenderException) {
            self::assertSame([], $this->provider->calls);
            self::assertCount(1, $audit->all());
        }
    }

    public function testRedactionObligationTransformsProviderInput(): void
    {
        $policy = new ConfigurablePolicyEngine(new PolicyDecision(
            PolicyAction::RedactAndContinue,
            obligations: ['redacted_input' => 'Email: [REDACTED]'],
        ));
        $audit = new InMemoryAuditLog();

        $this->gateway($policy, $audit)->infer(new InferenceRequest('Email: secret@example.test'), $this->context);

        self::assertSame('Email: [REDACTED]', $this->provider->calls[0]['input']);
        self::assertTrue($audit->all()[0]->metadata['policy_redacted']);
        self::assertSame('trace_1', $audit->all()[0]->traceUid);
    }

    public function testReviewDecisionPersistsAndNeverCallsProvider(): void
    {
        $policy = new ConfigurablePolicyEngine(new PolicyDecision(PolicyAction::RequiresReview, ['high_risk']));
        $audit = new InMemoryAuditLog();
        $reviews = new InMemoryReviewRepository();
        $gateway = $this->gateway($policy, $audit, reviewRepository: $reviews);

        try {
            $gateway->embed(new EmbeddingRequest('Review this'), context: $this->context);
            self::fail('Review-required execution should pause.');
        } catch (ReviewRequiredException $exception) {
            self::assertNotSame('', $exception->reviewUid);
            self::assertSame([], $this->provider->calls);
            self::assertCount(1, $reviews->all());
            self::assertSame('review_required', $audit->all()[0]->status);
            self::assertSame(ExecutionOperation::Embed, $policy->subjects[0]->operation);
        }
    }

    public function testFailedPostEvaluationBlocksResponseAndIsAudited(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = new AifGateway(
            providers: $this->providers,
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
            evaluationPipeline: new EvaluationPipeline([new FailingPostEvaluator()]),
        );

        try {
            $gateway->infer(new InferenceRequest('Generate unsafe output'), $this->context);
            self::fail('Failed evaluation should block the response.');
        } catch (EvaluationBlockedException) {
            self::assertCount(1, $this->provider->calls);
            self::assertCount(1, $audit->all());
            self::assertSame('error', $audit->all()[0]->status);
            self::assertSame(
                'fail',
                $audit->all()[0]->metadata['response']['evaluations'][1]['outcome'],
            );
        }
    }

    public function testProviderRoutingFailureIsAudited(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = new AifGateway(
            providers: new InMemoryProviderRegistry(['openai' => new OpenAICompatibleProvider('test-key')]),
            defaultProvider: 'openai',
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
        );

        try {
            foreach ($gateway->stream(new InferenceRequest('Stream this'), $this->context) as $event) {
            }
            self::fail('Unsupported provider routing should fail.');
        } catch (ProviderCapabilityException) {
            self::assertCount(1, $audit->all());
            self::assertSame('error', $audit->all()[0]->status);
            self::assertSame('stream', $audit->all()[0]->operation);
        }
    }

    public function testAuditFailureIsAttemptedOnceAndFailsTheExecution(): void
    {
        $audit = new FailingAuditLog();
        $gateway = new AifGateway(
            providers: $this->providers,
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
        );

        try {
            $gateway->infer(new InferenceRequest('Provider succeeds'), $this->context);
            self::fail('Required audit persistence failure must fail execution.');
        } catch (AuditPersistenceException $exception) {
            self::assertSame('Required audit evidence could not be persisted.', $exception->getMessage());
            self::assertSame(1, $audit->attempts);
            self::assertCount(1, $this->provider->calls);
        }
    }

    public function testSuccessfulExecutionEmitsVendorNeutralMetrics(): void
    {
        $metrics = new InMemoryMetricsCollector();
        $gateway = new AifGateway(
            providers: $this->providers,
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: new InMemoryAuditLog(),
            runtimeMode: RuntimeMode::Governed,
            metrics: $metrics,
        );

        $gateway->infer(new InferenceRequest('Measure this'), $this->context);

        $events = $metrics->all();
        $names = array_map(static fn ($event): string => $event->name, $events);
        self::assertContains('aif.provider.health', $names);
        self::assertContains('aif.execution', $names);
        self::assertContains('aif.execution.latency_ms', $names);
        self::assertContains('aif.stage.latency_ms', $names);

        $execution = $events[array_search('aif.execution', $names, true)];
        self::assertSame('ok', $execution->tags['status']);
    }

    private function gateway(
        StaticPolicyEngine|ConfigurablePolicyEngine $policy,
        InMemoryAuditLog $audit,
        ?InMemoryPromptRegistry $prompts = null,
        ?InMemoryReviewRepository $reviewRepository = null,
    ): AifGateway {
        return new AifGateway(
            providers: $this->providers,
            defaultProvider: 'recording',
            policyEngine: $policy,
            promptRegistry: $prompts,
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
            reviewRepository: $reviewRepository,
        );
    }
}
