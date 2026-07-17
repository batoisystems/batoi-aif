<?php

declare(strict_types=1);

namespace Batoi\Aif\Api;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
use Batoi\Aif\Exception\GovernanceConfigurationException;
use Batoi\Aif\Exception\EvaluationBlockedException;
use Batoi\Aif\Exception\AuditPersistenceException;
use Batoi\Aif\Exception\PolicyDeniedException;
use Batoi\Aif\Exception\PromptNotApprovedException;
use Batoi\Aif\Exception\PromptNotFoundException;
use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Exception\ProviderNotFoundException;
use Batoi\Aif\Exception\ProviderCapabilityException;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Exception\RequestValidationException;
use Batoi\Aif\Exception\ReviewRequiredException;
use Batoi\Aif\Exception\ReviewApprovalException;
use Batoi\Aif\Exception\CircuitBreakerOpenException;
use Batoi\Aif\Exception\ExecutionCancelledException;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\ModerationRequest;
use Throwable;

final readonly class AifApi
{
    public function __construct(
        private AifGateway $gateway,
        private ?ExecutionContextResolverInterface $contextResolver = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed $contextSource Host framework request/session/controller context.
     * @return array<string, mixed>
     */
    public function infer(array $payload, mixed $contextSource = null): array
    {
        try {
            $input = $this->requiredInput($payload, allowPrompt: true);
            $response = $this->gateway->infer(
                request: new InferenceRequest(
                    input: $input,
                    promptCode: $this->optionalString($payload, 'prompt_code'),
                    promptVersion: $this->optionalString($payload, 'prompt_version'),
                    provider: $this->optionalString($payload, 'provider'),
                    model: $this->optionalString($payload, 'model'),
                    variables: $this->arrayValue($payload, 'variables'),
                    metadata: $this->arrayValue($payload, 'metadata'),
                ),
                context: $this->contextResolver === null ? null : $this->contextResolver->resolve($contextSource),
            );

            return [
                'ok' => true,
                'data' => [
                    'request_uid' => $response->requestUid,
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'output' => $response->output,
                    'usage' => $response->usage,
                    'metadata' => $response->metadata,
                ],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->errorEnvelope($exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed $contextSource Host framework request/session/controller context.
     * @return array<string, mixed>
     */
    public function embed(array $payload, mixed $contextSource = null): array
    {
        try {
            $response = $this->gateway->embed(
                request: new EmbeddingRequest(
                    input: $this->requiredInput($payload),
                    model: $this->optionalString($payload, 'model'),
                    metadata: $this->arrayValue($payload, 'metadata'),
                ),
                provider: $this->optionalString($payload, 'provider'),
                context: $this->contextResolver === null ? null : $this->contextResolver->resolve($contextSource),
            );

            return [
                'ok' => true,
                'data' => [
                    'embedding' => $response->embedding,
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'usage' => $response->usage,
                    'metadata' => $response->metadata,
                ],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->errorEnvelope($exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed $contextSource Host framework request/session/controller context.
     * @return array<string, mixed>
     */
    public function moderate(array $payload, mixed $contextSource = null): array
    {
        try {
            $response = $this->gateway->moderate(
                request: new ModerationRequest(
                    input: $this->requiredInput($payload),
                    model: $this->optionalString($payload, 'model'),
                    metadata: $this->arrayValue($payload, 'metadata'),
                ),
                provider: $this->optionalString($payload, 'provider'),
                context: $this->contextResolver === null ? null : $this->contextResolver->resolve($contextSource),
            );

            return [
                'ok' => true,
                'data' => [
                    'flagged' => $response->flagged,
                    'categories' => $response->categories,
                    'metadata' => $response->metadata,
                ],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->errorEnvelope($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function errorEnvelope(Throwable $exception): array
    {
        [$code, $message, $httpStatus] = match (true) {
            $exception instanceof RequestValidationException => ['invalid_request', $exception->getMessage(), 422],
            $exception instanceof PolicyDeniedException => ['policy_denied', 'The request was denied by policy.', 403],
            $exception instanceof ReviewRequiredException => ['review_required', 'The request requires approval before execution.', 202],
            $exception instanceof ReviewApprovalException => ['review_invalid', 'The approval is invalid or already used.', 409],
            $exception instanceof ExecutionCancelledException => ['execution_cancelled', 'The request was cancelled.', 499],
            $exception instanceof CircuitBreakerOpenException => ['provider_circuit_open', 'The provider is temporarily unavailable.', 503],
            $exception instanceof PromptNotFoundException => ['prompt_not_found', 'The requested prompt was not found.', 404],
            $exception instanceof PromptNotApprovedException => ['prompt_not_approved', 'The requested prompt is not approved.', 403],
            $exception instanceof PromptRenderException => ['prompt_render_failed', 'The prompt input is invalid.', 422],
            $exception instanceof ProviderNotFoundException => ['provider_unavailable', 'The requested provider is unavailable.', 503],
            $exception instanceof ProviderCapabilityException => ['provider_capability_unavailable', 'No provider supports the requested operation.', 503],
            $exception instanceof ProviderRequestException => ['provider_request_failed', 'The AI provider request failed.', 502],
            $exception instanceof GovernanceConfigurationException => ['governance_unavailable', 'Governed execution is unavailable.', 503],
            $exception instanceof EvaluationBlockedException => ['evaluation_failed', 'The generated output did not pass evaluation.', 422],
            $exception instanceof AuditPersistenceException => ['audit_unavailable', 'Required audit evidence could not be persisted.', 503],
            default => ['internal_error', 'The request could not be completed.', 500],
        };

        $error = [
            'code' => $code,
            'message' => $message,
            'http_status' => $httpStatus,
        ];

        if ($exception instanceof ReviewRequiredException) {
            $error['details'] = ['review_uid' => $exception->reviewUid];
        }

        return [
            'ok' => false,
            'data' => null,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredInput(array $payload, bool $allowPrompt = false): string
    {
        $input = $payload['input'] ?? null;

        if ($input !== null && !is_string($input)) {
            throw new RequestValidationException('input', 'Field "input" must be a string.');
        }

        $input = trim((string) $input);
        $promptCode = $this->optionalString($payload, 'prompt_code');

        if ($input === '' && !($allowPrompt && $promptCode !== null)) {
            throw new RequestValidationException('input', 'Field "input" is required.');
        }

        return $input;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $field): ?string
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null) {
            return null;
        }

        if (!is_string($payload[$field]) || trim($payload[$field]) === '') {
            throw new RequestValidationException($field, sprintf('Field "%s" must be a non-empty string.', $field));
        }

        return trim($payload[$field]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $field): array
    {
        if (!array_key_exists($field, $payload)) {
            return [];
        }

        if (!is_array($payload[$field])) {
            throw new RequestValidationException($field, sprintf('Field "%s" must be an object or array.', $field));
        }

        return $payload[$field];
    }
}
