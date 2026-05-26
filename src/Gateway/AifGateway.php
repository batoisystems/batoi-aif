<?php

declare(strict_types=1);

namespace Batoi\Aif\Gateway;

use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Contracts\PolicyEngineInterface;
use Batoi\Aif\Contracts\PromptRegistryInterface;
use Batoi\Aif\Contracts\ProviderRegistryInterface;
use Batoi\Aif\Exception\PolicyDeniedException;
use Batoi\Aif\Prompts\PromptRenderer;
use Batoi\Aif\Value\AuditRecord;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\StreamEvent;
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
    ) {
    }

    public function infer(InferenceRequest $request, ?ExecutionContext $context = null): InferenceResponse
    {
        $request = $this->withResolvedProvider($this->resolvePrompt($request));
        $decision = null;

        try {
            $this->enforcePolicy($context, $request, $decision);

            $response = $this->providers
                ->get($request->provider)
                ->generateText($request);

            $this->audit($request, $response, $decision);

            return $response;
        } catch (Throwable $exception) {
            $this->audit($request, null, $decision, $exception);

            throw $exception;
        }
    }

    private function resolvePrompt(InferenceRequest $request): InferenceRequest
    {
        if ($request->promptCode === null || $this->promptRegistry === null) {
            return $request;
        }

        $renderer = $this->promptRenderer ?? new PromptRenderer();
        $prompt = $this->promptRegistry->get($request->promptCode, $request->promptVersion);

        return new InferenceRequest(
            input: $renderer->render($prompt, $request->variables),
            promptCode: $request->promptCode,
            promptVersion: $prompt->version,
            provider: $request->provider,
            model: $request->model,
            variables: $request->variables,
            metadata: $request->metadata,
        );
    }

    /**
     * @return iterable<StreamEvent>
     */
    public function stream(InferenceRequest $request, ?ExecutionContext $context = null): iterable
    {
        $request = $this->withResolvedProvider($this->resolvePrompt($request));
        $decision = null;
        $streamedContent = '';

        try {
            $this->enforcePolicy($context, $request, $decision);

            foreach ($this->providers->get($request->provider)->stream($request) as $event) {
                $streamedContent .= $event->content;

                yield $event;
            }

            $this->auditGeneric(
                request: $request,
                responsePayload: ['output' => $streamedContent],
                policyDecision: $decision,
                provider: $request->provider,
                model: $request->model,
                metadata: ['operation' => 'stream'],
            );
        } catch (Throwable $exception) {
            $this->audit($request, null, $decision, $exception);

            throw $exception;
        }
    }

    public function embed(EmbeddingRequest $request, ?string $provider = null, ?ExecutionContext $context = null): EmbeddingResponse
    {
        $auditRequest = new InferenceRequest(
            input: $request->input,
            provider: $provider ?? $this->defaultProvider,
            model: $request->model,
            metadata: ['operation' => 'embed'] + $request->metadata,
        );
        $decision = null;

        try {
            $this->enforcePolicy($context, $auditRequest, $decision);
            $response = $this->providers
                ->get($auditRequest->provider)
                ->generateEmbedding($request);

            $this->auditGeneric(
                request: $auditRequest,
                responsePayload: [
                    'embedding_dimensions' => count($response->embedding),
                    'provider' => $response->provider,
                    'model' => $response->model,
                ],
                policyDecision: $decision,
                provider: $response->provider,
                model: $response->model,
                usage: $response->usage,
                metadata: ['operation' => 'embed'] + $response->metadata,
            );

            return $response;
        } catch (Throwable $exception) {
            $this->audit($auditRequest, null, $decision, $exception);

            throw $exception;
        }
    }

    public function moderate(ModerationRequest $request, ?string $provider = null, ?ExecutionContext $context = null): ModerationResponse
    {
        $auditRequest = new InferenceRequest(
            input: $request->input,
            provider: $provider ?? $this->defaultProvider,
            model: $request->model,
            metadata: ['operation' => 'moderate'] + $request->metadata,
        );
        $decision = null;

        try {
            $this->enforcePolicy($context, $auditRequest, $decision);
            $response = $this->providers
                ->get($auditRequest->provider)
                ->moderate($request);

            $this->auditGeneric(
                request: $auditRequest,
                responsePayload: [
                    'flagged' => $response->flagged,
                    'categories' => $response->categories,
                ],
                policyDecision: $decision,
                provider: $auditRequest->provider,
                model: $request->model,
                metadata: ['operation' => 'moderate'] + $response->metadata,
            );

            return $response;
        } catch (Throwable $exception) {
            $this->audit($auditRequest, null, $decision, $exception);

            throw $exception;
        }
    }

    private function enforcePolicy(?ExecutionContext $context, InferenceRequest $request, ?PolicyDecision &$decision): void
    {
        if ($this->policyEngine === null || $context === null) {
            return;
        }

        $decision = $this->policyEngine->decide($context, $request);

        if (!$decision->allowsExecution()) {
            throw PolicyDeniedException::fromDecision($decision);
        }
    }

    private function withResolvedProvider(InferenceRequest $request): InferenceRequest
    {
        if ($request->provider !== null) {
            return $request;
        }

        return new InferenceRequest(
            input: $request->input,
            promptCode: $request->promptCode,
            promptVersion: $request->promptVersion,
            provider: $this->defaultProvider,
            model: $request->model,
            variables: $request->variables,
            metadata: $request->metadata,
        );
    }

    private function audit(
        InferenceRequest $request,
        ?InferenceResponse $response,
        mixed $policyDecision = null,
        ?Throwable $exception = null,
    ): void {
        if ($this->auditLog === null) {
            return;
        }

        $policy = $policyDecision === null ? [] : [
            'action' => $policyDecision->action->value,
            'reasons' => $policyDecision->reasons,
            'evidence' => $policyDecision->evidence,
        ];

        $this->auditLog->append(new AuditRecord(
            uid: $this->auditUid(),
            status: $this->auditStatus($exception),
            requestHash: $this->hash([
                'input' => $request->input,
                'prompt_code' => $request->promptCode,
                'prompt_version' => $request->promptVersion,
                'provider' => $request->provider,
                'model' => $request->model,
                'operation' => $request->metadata['operation'] ?? 'infer',
            ]),
            responseHash: $response === null ? null : $this->hash([
                'output' => $response->output,
                'provider' => $response->provider,
                'model' => $response->model,
            ]),
            provider: $response?->provider ?? $request->provider,
            model: $response?->model ?? $request->model,
            promptCode: $request->promptCode,
            promptVersion: $request->promptVersion,
            errorCode: $exception === null ? null : $exception::class,
            errorMessage: $exception?->getMessage(),
            policyDecision: $policy,
            usage: $response?->usage ?? [],
            metadata: [
                'operation' => $request->metadata['operation'] ?? 'infer',
            ],
        ));
    }

    /**
     * @param array<string, mixed> $responsePayload
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $metadata
     */
    private function auditGeneric(
        InferenceRequest $request,
        array $responsePayload,
        mixed $policyDecision = null,
        ?Throwable $exception = null,
        ?string $provider = null,
        ?string $model = null,
        array $usage = [],
        array $metadata = [],
    ): void {
        if ($this->auditLog === null) {
            return;
        }

        $policy = $policyDecision === null ? [] : [
            'action' => $policyDecision->action->value,
            'reasons' => $policyDecision->reasons,
            'evidence' => $policyDecision->evidence,
        ];

        $this->auditLog->append(new AuditRecord(
            uid: $this->auditUid(),
            status: $this->auditStatus($exception),
            requestHash: $this->hash([
                'input' => $request->input,
                'prompt_code' => $request->promptCode,
                'prompt_version' => $request->promptVersion,
                'provider' => $request->provider,
                'model' => $request->model,
                'operation' => $request->metadata['operation'] ?? ($metadata['operation'] ?? null),
            ]),
            responseHash: $exception === null ? $this->hash($responsePayload) : null,
            provider: $provider ?? $request->provider,
            model: $model ?? $request->model,
            promptCode: $request->promptCode,
            promptVersion: $request->promptVersion,
            errorCode: $exception === null ? null : $exception::class,
            errorMessage: $exception?->getMessage(),
            policyDecision: $policy,
            usage: $usage,
            metadata: $metadata,
        ));
    }

    private function auditStatus(?Throwable $exception): string
    {
        if ($exception instanceof PolicyDeniedException) {
            return 'denied';
        }

        return $exception === null ? 'ok' : 'error';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function auditUid(): string
    {
        return sprintf('audit_%s', bin2hex(random_bytes(8)));
    }
}
