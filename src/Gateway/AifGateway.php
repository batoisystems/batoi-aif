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
        $request = $this->resolvePrompt($request);
        $decision = null;

        try {
            if ($this->policyEngine !== null && $context !== null) {
                $decision = $this->policyEngine->decide($context, $request);

                if (!$decision->allowsExecution()) {
                    throw PolicyDeniedException::fromDecision($decision);
                }
            }

            $response = $this->providers
                ->get($request->provider ?? $this->defaultProvider)
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
    public function stream(InferenceRequest $request): iterable
    {
        return $this->providers
            ->get($request->provider ?? $this->defaultProvider)
            ->stream($request);
    }

    public function embed(EmbeddingRequest $request, ?string $provider = null): EmbeddingResponse
    {
        return $this->providers
            ->get($provider ?? $this->defaultProvider)
            ->generateEmbedding($request);
    }

    public function moderate(ModerationRequest $request, ?string $provider = null): ModerationResponse
    {
        return $this->providers
            ->get($provider ?? $this->defaultProvider)
            ->moderate($request);
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
            status: $exception === null ? 'ok' : 'error',
            requestHash: $this->hash([
                'input' => $request->input,
                'prompt_code' => $request->promptCode,
                'prompt_version' => $request->promptVersion,
                'provider' => $request->provider,
                'model' => $request->model,
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
        ));
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
