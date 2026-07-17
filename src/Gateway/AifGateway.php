<?php

declare(strict_types=1);

namespace Batoi\Aif\Gateway;

use Batoi\Aif\Audit\KeyNameSensitiveDataClassifier;
use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Contracts\AccessControlledVectorStoreInterface;
use Batoi\Aif\Contracts\OperationAwarePolicyEngineInterface;
use Batoi\Aif\Contracts\PolicyEngineInterface;
use Batoi\Aif\Contracts\PromptRegistryInterface;
use Batoi\Aif\Contracts\ProviderRegistryInterface;
use Batoi\Aif\Contracts\ReviewRepositoryInterface;
use Batoi\Aif\Contracts\ReviewWorkflowRepositoryInterface;
use Batoi\Aif\Contracts\MetricsCollectorInterface;
use Batoi\Aif\Contracts\PermissionCheckerInterface;
use Batoi\Aif\Contracts\SensitiveDataClassifierInterface;
use Batoi\Aif\Contracts\ToolInterface;
use Batoi\Aif\Contracts\VectorStoreInterface;
use Batoi\Aif\Exception\GovernanceConfigurationException;
use Batoi\Aif\Exception\AuditPersistenceException;
use Batoi\Aif\Exception\EvaluationBlockedException;
use Batoi\Aif\Exception\PolicyDeniedException;
use Batoi\Aif\Exception\PolicyObligationException;
use Batoi\Aif\Exception\ReviewRequiredException;
use Batoi\Aif\Exception\ReviewApprovalException;
use Batoi\Aif\Exception\RequestValidationException;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Policy\PolicyAction;
use Batoi\Aif\Evaluation\EvaluationPipeline;
use Batoi\Aif\Evaluation\EvaluationStage;
use Batoi\Aif\Prompts\PromptRenderer;
use Batoi\Aif\Providers\ProviderRouter;
use Batoi\Aif\Tools\ToolSideEffect;
use Batoi\Aif\Value\AuditRecord;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\EvaluationResult;
use Batoi\Aif\Value\EvaluationSubject;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\MetricEvent;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\PolicySubject;
use Batoi\Aif\Value\ReviewRequest;
use Batoi\Aif\Value\StreamEvent;
use Batoi\Aif\Value\ToolResult;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Value\VectorSearchResult;
use Throwable;

final readonly class AifGateway
{
    public function __construct(
        private ProviderRegistryInterface $providers,
        private string $defaultProvider = 'mock',
        private ?PolicyEngineInterface $policyEngine = null,
        private ?PromptRegistryInterface $promptRegistry = null,
        private ?PromptRenderer $promptRenderer = null,
        private ?AuditLogInterface $auditLog = null,
        private RuntimeMode $runtimeMode = RuntimeMode::Development,
        private ?ReviewRepositoryInterface $reviewRepository = null,
        private ?ProviderRouter $providerRouter = null,
        private ?EvaluationPipeline $evaluationPipeline = null,
        private ?MetricsCollectorInterface $metrics = null,
        private ?PermissionCheckerInterface $permissionChecker = null,
        private ?SensitiveDataClassifierInterface $sensitiveDataClassifier = null,
    ) {
    }

    public function infer(InferenceRequest $request, ?ExecutionContext $context = null): InferenceResponse
    {
        $operation = ExecutionOperation::Infer;
        $startedAt = hrtime(true);
        $prepared = $request;
        $decision = null;
        $response = null;
        $exception = null;
        $evaluations = [];

        try {
            $this->assertGovernedDependencies($context);
            $prepared = $this->withResolvedProvider($prepared, $operation);
            $prepared = $this->resolvePrompt($prepared);
            $prepared = $this->enforcePolicy($operation, $context, $prepared, $decision);
            $evaluations = $this->evaluate(EvaluationStage::PreExecution, $operation, $prepared, $context);
            $response = $this->providers->get((string) $prepared->provider)->generateText($prepared);
            $evaluations = array_merge(
                $evaluations,
                $this->evaluate(
                    EvaluationStage::PostExecution,
                    $operation,
                    $prepared,
                    $context,
                    ['text' => $response->output],
                ),
            );

            return $response;
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $prepared,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $response === null ? null : [
                    'output' => $response->output,
                    'provider' => $response->provider,
                    'model' => $response->model,
                ],
                provider: $response?->provider,
                model: $response?->model,
                usage: $response === null ? [] : $response->usage,
                responseMetadata: ['evaluations' => $this->evaluationEvidence($evaluations, $exception)]
                    + ($response === null ? [] : $response->metadata),
                exception: $exception,
                startedAt: $startedAt,
                providerRequestUid: $response?->requestUid,
            );
        }
    }

    /** @return iterable<StreamEvent> */
    public function stream(InferenceRequest $request, ?ExecutionContext $context = null): iterable
    {
        $operation = ExecutionOperation::Stream;
        $startedAt = hrtime(true);
        $prepared = $request;
        $decision = null;
        $exception = null;
        $streamedContent = '';
        $evaluations = [];

        try {
            $this->assertGovernedDependencies($context);
            $prepared = $this->withResolvedProvider($prepared, $operation);
            $prepared = $this->resolvePrompt($prepared);
            $prepared = $this->enforcePolicy($operation, $context, $prepared, $decision);
            $evaluations = $this->evaluate(EvaluationStage::PreExecution, $operation, $prepared, $context);

            foreach ($this->providers->get((string) $prepared->provider)->stream($prepared) as $event) {
                $streamedContent .= $event->content;
                yield $event;
            }

            $evaluations = array_merge(
                $evaluations,
                $this->evaluate(
                    EvaluationStage::PostExecution,
                    $operation,
                    $prepared,
                    $context,
                    ['text' => $streamedContent],
                ),
            );
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $prepared,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $exception === null ? ['output' => $streamedContent] : null,
                provider: $prepared->provider,
                model: $prepared->model,
                responseMetadata: ['evaluations' => $this->evaluationEvidence($evaluations, $exception)],
                exception: $exception,
                startedAt: $startedAt,
            );
        }
    }

    public function embed(EmbeddingRequest $request, ?string $provider = null, ?ExecutionContext $context = null): EmbeddingResponse
    {
        $operation = ExecutionOperation::Embed;
        $startedAt = hrtime(true);
        $auditRequest = new InferenceRequest(
            input: $request->input,
            provider: $provider,
            model: $request->model,
            metadata: $request->metadata,
        );
        $decision = null;
        $response = null;
        $exception = null;
        $evaluations = [];

        try {
            $this->assertGovernedDependencies($context);
            $auditRequest = $this->withResolvedProvider($auditRequest, $operation);
            $auditRequest = $this->enforcePolicy($operation, $context, $auditRequest, $decision);
            $evaluations = $this->evaluate(EvaluationStage::PreExecution, $operation, $auditRequest, $context);
            $response = $this->providers->get((string) $auditRequest->provider)->generateEmbedding(new EmbeddingRequest(
                input: $auditRequest->input,
                model: $auditRequest->model,
                metadata: $request->metadata,
            ));
            $evaluations = array_merge(
                $evaluations,
                $this->evaluate(
                    EvaluationStage::PostExecution,
                    $operation,
                    $auditRequest,
                    $context,
                    ['embedding_dimensions' => count($response->embedding)],
                ),
            );

            return $response;
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $auditRequest,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $response === null ? null : [
                    'embedding_dimensions' => count($response->embedding),
                    'provider' => $response->provider,
                    'model' => $response->model,
                ],
                provider: $response?->provider,
                model: $response?->model,
                usage: $response === null ? [] : $response->usage,
                responseMetadata: ['evaluations' => $this->evaluationEvidence($evaluations, $exception)]
                    + ($response === null ? [] : $response->metadata),
                exception: $exception,
                startedAt: $startedAt,
            );
        }
    }

    public function moderate(ModerationRequest $request, ?string $provider = null, ?ExecutionContext $context = null): ModerationResponse
    {
        $operation = ExecutionOperation::Moderate;
        $startedAt = hrtime(true);
        $auditRequest = new InferenceRequest(
            input: $request->input,
            provider: $provider,
            model: $request->model,
            metadata: $request->metadata,
        );
        $decision = null;
        $response = null;
        $exception = null;
        $evaluations = [];

        try {
            $this->assertGovernedDependencies($context);
            $auditRequest = $this->withResolvedProvider($auditRequest, $operation);
            $auditRequest = $this->enforcePolicy($operation, $context, $auditRequest, $decision);
            $evaluations = $this->evaluate(EvaluationStage::PreExecution, $operation, $auditRequest, $context);
            $response = $this->providers->get((string) $auditRequest->provider)->moderate(new ModerationRequest(
                input: $auditRequest->input,
                model: $auditRequest->model,
                metadata: $request->metadata,
            ));
            $evaluations = array_merge(
                $evaluations,
                $this->evaluate(
                    EvaluationStage::PostExecution,
                    $operation,
                    $auditRequest,
                    $context,
                    ['flagged' => $response->flagged, 'categories' => $response->categories],
                ),
            );

            return $response;
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $auditRequest,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $response === null ? null : [
                    'flagged' => $response->flagged,
                    'categories' => $response->categories,
                ],
                provider: $auditRequest->provider,
                model: $auditRequest->model,
                responseMetadata: ['evaluations' => $this->evaluationEvidence($evaluations, $exception)]
                    + ($response === null ? [] : $response->metadata),
                exception: $exception,
                startedAt: $startedAt,
            );
        }
    }

    /** @return list<VectorSearchResult> */
    public function retrieve(
        VectorSearchRequest $request,
        VectorStoreInterface $vectorStore,
        ExecutionContext $context,
    ): array {
        $operation = ExecutionOperation::Retrieve;
        $startedAt = hrtime(true);
        $auditRequest = new InferenceRequest(
            input: sprintf('%s:%s', $request->collection, hash('sha256', serialize($request->normalizedVector()))),
            metadata: [
                'operation' => $operation->value,
                'top_k' => $request->topK,
                'min_score' => $request->minScore,
            ],
        );
        $decision = null;
        $exception = null;
        $results = [];

        try {
            $this->assertGovernedDependencies($context);
            $auditRequest = $this->enforcePolicy($operation, $context, $auditRequest, $decision);
            $governedRequest = new VectorSearchRequest(
                collection: $request->collection,
                vector: $request->vector,
                topK: $request->topK,
                minScore: $request->minScore,
                filters: ['space_id' => $context->workspaceId] + $request->filters,
            );
            if ($this->runtimeMode === RuntimeMode::Governed && !$vectorStore instanceof AccessControlledVectorStoreInterface) {
                throw new GovernanceConfigurationException(
                    'Governed retrieval requires a vector adapter with workspace and ACL filter pushdown.',
                );
            }

            $results = $vectorStore instanceof AccessControlledVectorStoreInterface
                ? $vectorStore->searchGoverned($governedRequest, $context)
                : $vectorStore->search($governedRequest);

            foreach ($results as $result) {
                if (($result->record->metadata['space_id'] ?? null) !== $context->workspaceId) {
                    throw new GovernanceConfigurationException('Vector store returned a record outside the governed workspace.');
                }
            }

            $results = array_values(array_filter(
                $results,
                fn (VectorSearchResult $result): bool => $this->canAccessVectorRecord($result, $context),
            ));

            return $results;
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $auditRequest,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $exception === null ? [
                    'results' => array_map(
                        static fn (VectorSearchResult $result): array => [
                            'id' => $result->record->id,
                            'score' => $result->score,
                        ],
                        $results,
                    ),
                ] : null,
                provider: 'vector_store',
                model: null,
                exception: $exception,
                startedAt: $startedAt,
            );
        }
    }

    /** @param array<string, mixed> $arguments */
    public function executeTool(
        ToolInterface $tool,
        array $arguments,
        ExecutionContext $context,
        ?string $reviewUid = null,
    ): ToolResult {
        $operation = ExecutionOperation::Tool;
        $definition = $tool->definition();
        $startedAt = hrtime(true);
        $auditRequest = new InferenceRequest(
            input: 'tool_arguments_unavailable',
            metadata: [
                'operation' => $operation->value,
                'tool_code' => $definition->code,
                'tool_side_effect' => $definition->sideEffect->value,
            ],
        );
        $decision = null;
        $exception = null;
        $result = null;
        $evaluations = [];
        $reviewApproved = false;

        try {
            $this->assertGovernedDependencies($context);
            $auditRequest = new InferenceRequest(
                input: json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                metadata: $auditRequest->metadata,
            );
            $auditRequest = $this->enforcePolicy(
                $operation,
                $context,
                $auditRequest,
                $decision,
                $reviewUid,
                $reviewApproved,
            );

            if (($auditRequest->metadata['policy_redacted'] ?? false) === true) {
                $decoded = json_decode($auditRequest->input, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new PolicyObligationException('Tool redaction must produce a JSON object.');
                }
                $arguments = $decoded;
            }

            foreach ($definition->requiredArguments as $argument) {
                if (!array_key_exists($argument, $arguments)) {
                    throw new RequestValidationException($argument, sprintf('Required tool argument is missing: %s', $argument));
                }
            }

            if ($definition->permission !== null) {
                if ($this->permissionChecker === null) {
                    throw new GovernanceConfigurationException('Permission-gated tools require a permission checker.');
                }

                if (!$this->permissionChecker->can($context, $definition->permission, ['tool' => $definition->code])) {
                    $decision = new PolicyDecision(
                        PolicyAction::Deny,
                        ['tool_permission_denied'],
                        ['permission' => $definition->permission, 'tool' => $definition->code],
                    );
                    throw PolicyDeniedException::fromDecision($decision);
                }
            }

            if ($definition->requiresReview && !$reviewApproved) {
                $decision = new PolicyDecision(
                    PolicyAction::RequiresReview,
                    ['tool_requires_review'],
                    ['tool' => $definition->code],
                );
                if ($reviewUid === null) {
                    throw $this->createReview($operation, $context, $auditRequest, $decision);
                }

                $this->consumeReview($reviewUid, $operation, $context, $auditRequest);
                $reviewApproved = true;
            }

            if (
                !$definition->idempotent
                && $definition->sideEffect !== ToolSideEffect::None
                && !is_string($arguments['idempotency_key'] ?? null)
            ) {
                throw new RequestValidationException(
                    'idempotency_key',
                    'Non-idempotent side-effecting tools require an idempotency key.',
                );
            }

            $evaluations = $this->evaluate(EvaluationStage::PreExecution, $operation, $auditRequest, $context);
            $result = $tool->execute($arguments, $context);
            $evaluations = array_merge(
                $evaluations,
                $this->evaluate(
                    EvaluationStage::PostExecution,
                    $operation,
                    $auditRequest,
                    $context,
                    $result->output,
                ),
            );

            return $result;
        } catch (Throwable $caught) {
            $exception = $caught;
            throw $caught;
        } finally {
            $this->audit(
                request: $auditRequest,
                operation: $operation,
                context: $context,
                policyDecision: $decision,
                responsePayload: $result?->output,
                provider: 'tool:' . $definition->code,
                model: null,
                responseMetadata: [
                    'tool' => $result === null ? [] : $result->metadata,
                    'evaluations' => $this->evaluationEvidence($evaluations, $exception),
                ],
                exception: $exception,
                startedAt: $startedAt,
            );
        }
    }

    private function assertGovernedDependencies(?ExecutionContext $context): void
    {
        if ($this->runtimeMode !== RuntimeMode::Governed) {
            return;
        }

        if ($this->auditLog === null) {
            throw new GovernanceConfigurationException('Governed mode requires an audit log.');
        }

        if ($this->policyEngine === null) {
            throw new GovernanceConfigurationException('Governed mode requires a policy engine.');
        }

        if ($context === null) {
            throw new GovernanceConfigurationException('Governed mode requires execution context.');
        }
    }

    private function canAccessVectorRecord(VectorSearchResult $result, ExecutionContext $context): bool
    {
        $metadata = $result->record->metadata;
        if (($metadata['acl_visibility'] ?? 'public') === 'public') {
            return true;
        }

        $users = $metadata['acl_user_ids'] ?? [];
        $roles = $metadata['acl_roles'] ?? [];
        if (!is_array($users) || !is_array($roles)) {
            return false;
        }

        return in_array($context->userId, $users, true)
            || array_intersect($context->roles, $roles) !== [];
    }

    /**
     * @param array<string, mixed> $output
     * @return list<EvaluationResult>
     */
    private function evaluate(
        EvaluationStage $stage,
        ExecutionOperation $operation,
        InferenceRequest $request,
        ?ExecutionContext $context,
        array $output = [],
    ): array {
        if ($this->evaluationPipeline === null) {
            return [];
        }

        return $this->evaluationPipeline->evaluate(new EvaluationSubject(
            stage: $stage,
            operation: $operation,
            input: $request->input,
            output: $output,
            context: $context,
        ));
    }

    /**
     * @param list<EvaluationResult> $results
     * @return list<array<string, mixed>>
     */
    private function evaluationEvidence(array $results, ?Throwable $exception): array
    {
        if ($exception instanceof EvaluationBlockedException) {
            $results = array_merge($results, $exception->results);
        }

        return array_map(
            static fn (EvaluationResult $result): array => $result->toArray(),
            $results,
        );
    }

    private function resolvePrompt(InferenceRequest $request): InferenceRequest
    {
        if ($request->promptCode === null) {
            return $request;
        }

        if ($this->promptRegistry === null) {
            throw new GovernanceConfigurationException('Prompt execution requires a prompt registry.');
        }

        $renderer = $this->promptRenderer ?? new PromptRenderer();
        $prompt = $this->promptRegistry->get($request->promptCode, $request->promptVersion);

        return $this->copyRequest(
            request: $request,
            input: $renderer->render($prompt, $request->variables),
            promptVersion: $prompt->version,
        );
    }

    private function enforcePolicy(
        ExecutionOperation $operation,
        ?ExecutionContext $context,
        InferenceRequest $request,
        ?PolicyDecision &$decision,
        ?string $reviewUid = null,
        ?bool &$reviewApproved = null,
    ): InferenceRequest {
        if ($this->policyEngine === null || $context === null) {
            return $request;
        }

        $decision = $this->policyEngine instanceof OperationAwarePolicyEngineInterface
            ? $this->policyEngine->decideForOperation($context, new PolicySubject($operation, $request))
            : $this->policyEngine->decide($context, $request);

        if ($decision->action === PolicyAction::Deny) {
            throw PolicyDeniedException::fromDecision($decision);
        }

        if ($decision->action === PolicyAction::RequiresReview) {
            if ($reviewUid === null) {
                throw $this->createReview($operation, $context, $request, $decision);
            }

            $this->consumeReview($reviewUid, $operation, $context, $request);
            $reviewApproved = true;
        }

        if ($decision->action === PolicyAction::RedactAndContinue) {
            $redactedInput = $decision->redactedInput();

            if ($redactedInput === null) {
                throw new PolicyObligationException('Redact-and-continue requires a redacted_input obligation.');
            }

            return $this->copyRequest(
                request: $request,
                input: $redactedInput,
                metadata: ['policy_redacted' => true] + $request->metadata,
            );
        }

        return $request;
    }

    private function createReview(
        ExecutionOperation $operation,
        ExecutionContext $context,
        InferenceRequest $request,
        PolicyDecision $decision,
    ): ReviewRequiredException {
        if ($this->reviewRepository === null) {
            throw new GovernanceConfigurationException('Review-required decisions require a review repository.');
        }

        $reviewUid = sprintf('review_%s', bin2hex(random_bytes(8)));
        $this->reviewRepository->append(new ReviewRequest(
            uid: $reviewUid,
            operation: $operation,
            requestHash: $this->hash($this->requestPayload($request, $operation)),
            userId: $context->userId,
            workspaceId: $context->workspaceId,
            policyEvidence: [
                'reasons' => $decision->reasons,
                'evidence' => $decision->evidence,
            ],
        ));

        return new ReviewRequiredException($reviewUid);
    }

    private function consumeReview(
        string $reviewUid,
        ExecutionOperation $operation,
        ExecutionContext $context,
        InferenceRequest $request,
    ): void {
        if (!$this->reviewRepository instanceof ReviewWorkflowRepositoryInterface) {
            throw new GovernanceConfigurationException('Approved execution requires a review workflow repository.');
        }

        $review = $this->reviewRepository->consumeApproved(
            $reviewUid,
            $context->workspaceId,
            $this->hash($this->requestPayload($request, $operation)),
        );

        if ($review === null || $review->operation !== $operation) {
            throw new ReviewApprovalException();
        }
    }

    private function withResolvedProvider(InferenceRequest $request, ExecutionOperation $operation): InferenceRequest
    {
        $route = ($this->providerRouter ?? new ProviderRouter(
            $this->providers,
            $this->defaultProvider,
            $this->metrics,
        ))->route(
            $operation,
            $request->provider,
            $request->model,
        );

        return $this->copyRequest(
            request: $request,
            provider: $route->provider,
            metadata: [
                'operation' => $operation->value,
                'routing_reason' => $route->reason,
            ] + $request->metadata,
        );
    }

    /**
     * Null arguments preserve the current nullable request property. Input is required because it is the
     * only field transformed by prompt and policy obligations.
     *
     * @param array<string, mixed>|null $metadata
     */
    private function copyRequest(
        InferenceRequest $request,
        ?string $input = null,
        ?string $promptVersion = null,
        ?string $provider = null,
        ?array $metadata = null,
    ): InferenceRequest {
        return new InferenceRequest(
            input: $input ?? $request->input,
            promptCode: $request->promptCode,
            promptVersion: $promptVersion ?? $request->promptVersion,
            provider: $provider ?? $request->provider,
            model: $request->model,
            variables: $request->variables,
            metadata: $metadata ?? $request->metadata,
        );
    }

    /**
     * @param array<string, mixed>|null $responsePayload
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $responseMetadata
     */
    private function audit(
        InferenceRequest $request,
        ExecutionOperation $operation,
        ?ExecutionContext $context,
        ?PolicyDecision $policyDecision,
        ?array $responsePayload,
        ?string $provider,
        ?string $model,
        array $usage = [],
        array $responseMetadata = [],
        ?Throwable $exception = null,
        int $startedAt = 0,
        ?string $providerRequestUid = null,
    ): void {
        if ($this->auditLog === null) {
            return;
        }

        $policy = $policyDecision === null ? [] : [
            'action' => $policyDecision->action->value,
            'reasons' => $policyDecision->reasons,
            'evidence' => $policyDecision->evidence,
            'obligations' => array_keys($policyDecision->obligations),
        ];

        $record = new AuditRecord(
            uid: $this->auditUid(),
            status: $this->auditStatus($exception),
            requestHash: $this->hash($this->requestPayload($request, $operation)),
            responseHash: $responsePayload === null ? null : $this->hash($responsePayload),
            provider: $provider ?? $request->provider,
            model: $model ?? $request->model,
            promptCode: $request->promptCode,
            promptVersion: $request->promptVersion,
            errorCode: $exception === null ? null : $exception::class,
            errorMessage: $exception?->getMessage(),
            policyDecision: $policy,
            usage: $usage,
            metadata: [
                'operation' => $operation->value,
                'policy_redacted' => (bool) ($request->metadata['policy_redacted'] ?? false),
                'response' => $responseMetadata,
                'routing_reason' => $request->metadata['routing_reason'] ?? null,
                'retrieval_evidence' => $request->metadata['retrieval_evidence'] ?? null,
                'retrieval_collection' => $request->metadata['retrieval_collection'] ?? null,
                'data_classification' => ($this->sensitiveDataClassifier ?? new KeyNameSensitiveDataClassifier())->classify(
                    $this->requestPayload($request, $operation),
                )->evidence(),
            ],
            userId: $context?->userId,
            workspaceId: $context?->workspaceId,
            traceUid: $context?->traceUid,
            operation: $operation->value,
            createdAt: gmdate(DATE_ATOM),
            latencyMs: $startedAt > 0 ? (int) ((hrtime(true) - $startedAt) / 1_000_000) : null,
            providerRequestUid: $providerRequestUid,
            policyVersion: is_string($policyDecision?->evidence['policy_version'] ?? null)
                ? $policyDecision->evidence['policy_version']
                : null,
        );

        try {
            $this->auditLog->append($record);
        } catch (Throwable $auditFailure) {
            $this->recordMetric('aif.audit.failure', 1, [
                'operation' => $operation->value,
            ]);
            throw new AuditPersistenceException($auditFailure, $exception);
        }

        $this->recordMetric('aif.execution', 1, [
            'operation' => $operation->value,
            'status' => $record->status,
            'provider' => $record->provider ?? 'none',
        ]);
        $this->recordMetric('aif.execution.latency_ms', (float) ($record->latencyMs ?? 0), [
            'operation' => $operation->value,
            'provider' => $record->provider ?? 'none',
        ]);
        $this->recordMetric('aif.stage.latency_ms', (float) ($record->latencyMs ?? 0), [
            'stage' => 'governed_execution',
            'operation' => $operation->value,
            'provider' => $record->provider ?? 'none',
        ]);

        if ($record->status === 'denied') {
            $this->recordMetric('aif.policy.denial', 1, ['operation' => $operation->value]);
        }

        if ($record->status === 'review_required') {
            $this->recordMetric('aif.review.required', 1, ['operation' => $operation->value]);
        }

        foreach (['input_tokens', 'output_tokens', 'total_tokens', 'prompt_tokens', 'completion_tokens', 'cost'] as $metric) {
            $value = $usage[$metric] ?? null;
            if (is_int($value) || is_float($value)) {
                $this->recordMetric('aif.usage.' . $metric, (float) $value, [
                    'operation' => $operation->value,
                    'provider' => $record->provider ?? 'none',
                ]);
            }
        }
    }

    private function auditStatus(?Throwable $exception): string
    {
        return match (true) {
            $exception instanceof PolicyDeniedException => 'denied',
            $exception instanceof ReviewRequiredException => 'review_required',
            $exception === null => 'ok',
            default => 'error',
        };
    }

    /** @return array<string, mixed> */
    private function requestPayload(InferenceRequest $request, ExecutionOperation $operation): array
    {
        return [
            'operation' => $operation->value,
            'input' => $request->input,
            'prompt_code' => $request->promptCode,
            'prompt_version' => $request->promptVersion,
            'provider' => $request->provider,
            'model' => $request->model,
            'variables' => $request->variables,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', (string) json_encode($this->canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function auditUid(): string
    {
        return sprintf('audit_%s', bin2hex(random_bytes(8)));
    }

    /** @param array<string, string> $tags */
    private function recordMetric(string $name, int|float $value, array $tags): void
    {
        $this->metrics?->record(new MetricEvent(
            name: $name,
            value: (float) $value,
            tags: $tags,
            occurredAt: gmdate(DATE_ATOM),
        ));
    }
}
