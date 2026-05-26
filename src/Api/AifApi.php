<?php

declare(strict_types=1);

namespace Batoi\Aif\Api;

use Batoi\Aif\Contracts\ExecutionContextResolverInterface;
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
            $response = $this->gateway->infer(
                request: new InferenceRequest(
                    input: (string) ($payload['input'] ?? ''),
                    promptCode: isset($payload['prompt_code']) ? (string) $payload['prompt_code'] : null,
                    promptVersion: isset($payload['prompt_version']) ? (string) $payload['prompt_version'] : null,
                    provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
                    model: isset($payload['model']) ? (string) $payload['model'] : null,
                    variables: $this->arrayValue($payload['variables'] ?? []),
                    metadata: $this->arrayValue($payload['metadata'] ?? []),
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
                    input: (string) ($payload['input'] ?? ''),
                    model: isset($payload['model']) ? (string) $payload['model'] : null,
                    metadata: $this->arrayValue($payload['metadata'] ?? []),
                ),
                provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
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
                    input: (string) ($payload['input'] ?? ''),
                    model: isset($payload['model']) ? (string) $payload['model'] : null,
                    metadata: $this->arrayValue($payload['metadata'] ?? []),
                ),
                provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
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
        return [
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
