<?php

declare(strict_types=1);

namespace Batoi\Aif\Providers;

use Batoi\Aif\Contracts\CapabilityAwareProviderInterface;
use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Exception\StreamingNotSupportedException;
use Batoi\Aif\Http\CurlHttpTransport;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\EmbeddingResponse;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\InferenceResponse;
use Batoi\Aif\Value\ModerationRequest;
use Batoi\Aif\Value\ModerationResponse;
use Batoi\Aif\Value\ProviderCapability;

final readonly class OpenAICompatibleProvider implements CapabilityAwareProviderInterface
{
    private HttpTransportInterface $transport;

    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private string $providerCode = 'openai',
        private string $defaultTextModel = 'gpt-4.1-mini',
        private string $defaultEmbeddingModel = 'text-embedding-3-small',
        private string $defaultModerationModel = 'omni-moderation-latest',
        ?HttpTransportInterface $transport = null,
        private int $timeoutSeconds = 30,
        private ?string $textEndpoint = null,
    ) {
        $this->transport = $transport ?? new CurlHttpTransport();
    }

    public function generateText(InferenceRequest $request): InferenceResponse
    {
        $model = $request->model ?? $this->defaultTextModel;
        $messages = $request->metadata['messages'] ?? [];
        if (!is_array($messages) || $messages === []) {
            $messages = [['role' => 'user', 'content' => $request->input]];
        }
        $endpoint = $this->resolvedTextEndpoint();
        $isResponses = str_contains($endpoint, '/responses');
        $payload = $isResponses
            ? [
                'model' => $model,
                'input' => $this->responsesInput($messages),
                'max_output_tokens' => $request->metadata['max_tokens'] ?? null,
                'temperature' => $request->metadata['temperature'] ?? null,
            ]
            : [
                'model' => $model,
                'messages' => $messages,
                'max_completion_tokens' => $request->metadata['max_tokens'] ?? null,
                'temperature' => $request->metadata['temperature'] ?? 0.2,
            ];
        $result = $this->postUrl($endpoint, array_filter($payload, static fn (mixed $value): bool => $value !== null));
        $data = $result['data'];
        $output = $this->extractText($data);

        return new InferenceResponse(
            output: $output,
            provider: $this->providerCode,
            model: (string) ($data['model'] ?? $model),
            requestUid: (string) ($data['id'] ?? $result['metadata']['request_id'] ?? $this->requestUid()),
            usage: $this->arrayValue($data['usage'] ?? []),
            metadata: [
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                'transport' => $result['metadata'],
            ],
        );
    }

    public function stream(InferenceRequest $request): iterable
    {
        throw StreamingNotSupportedException::forProvider($this->providerCode);
    }

    public function generateEmbedding(EmbeddingRequest $request): EmbeddingResponse
    {
        $model = $request->model ?? $this->defaultEmbeddingModel;
        $result = $this->post('/embeddings', [
            'model' => $model,
            'input' => $request->input,
        ]);

        $data = $result['data'];

        return new EmbeddingResponse(
            embedding: $this->floatList($data['data'][0]['embedding'] ?? []),
            provider: $this->providerCode,
            model: (string) ($data['model'] ?? $model),
            usage: $this->arrayValue($data['usage'] ?? []),
            metadata: ['transport' => $result['metadata']],
        );
    }

    public function moderate(ModerationRequest $request): ModerationResponse
    {
        $response = $this->post('/moderations', [
            'model' => $request->model ?? $this->defaultModerationModel,
            'input' => $request->input,
        ]);
        $result = $this->arrayValue($response['data']['results'][0] ?? []);
        $categories = [];

        foreach ($this->arrayValue($result['categories'] ?? []) as $name => $flagged) {
            if ($flagged === true) {
                $categories[] = (string) $name;
            }
        }

        return new ModerationResponse(
            flagged: (bool) ($result['flagged'] ?? false),
            categories: $categories,
            metadata: $result + ['transport' => $response['metadata']],
        );
    }

    public function healthCheck(): bool
    {
        return $this->apiKey !== '';
    }

    public function capabilities(): array
    {
        return [
            new ProviderCapability(
                $this->providerCode,
                $this->defaultTextModel,
                ['text'],
                ['streaming' => false, 'accepts_model_override' => true],
            ),
            new ProviderCapability(
                $this->providerCode,
                $this->defaultEmbeddingModel,
                ['embedding'],
                ['accepts_model_override' => true],
            ),
            new ProviderCapability(
                $this->providerCode,
                $this->defaultModerationModel,
                ['moderation'],
                ['accepts_model_override' => true],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{data: array<string, mixed>, metadata: array<string, string>}
     */
    private function post(string $path, array $payload): array
    {
        return $this->postUrl(rtrim($this->baseUrl, '/') . $path, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{data: array<string, mixed>, metadata: array<string, string>}
     */
    private function postUrl(string $url, array $payload): array
    {
        $response = $this->transport->postJson(
            url: $url,
            headers: [
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            payload: $payload,
            timeoutSeconds: $this->timeoutSeconds,
        );
        $data = json_decode($response->body, true);

        if (!is_array($data)) {
            throw ProviderRequestException::failed($this->providerCode, $response->statusCode, 'Invalid JSON response.');
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            $message = (string) ($data['error']['message'] ?? 'Provider request failed.');

            throw ProviderRequestException::failed($this->providerCode, $response->statusCode, $message);
        }

        return [
            'data' => $data,
            'metadata' => $this->transportMetadata($response->headers),
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function transportMetadata(array $headers): array
    {
        $allowed = [
            'x-request-id',
            'retry-after',
            'x-ratelimit-remaining-requests',
            'x-ratelimit-remaining-tokens',
            'x-ratelimit-reset-requests',
            'x-ratelimit-reset-tokens',
        ];
        $metadata = [];

        foreach ($allowed as $name) {
            if (isset($headers[$name])) {
                $metadata[str_replace(['x-', '-'], ['', '_'], $name)] = $headers[$name];
            }
        }

        return $metadata;
    }

    private function resolvedTextEndpoint(): string
    {
        $endpoint = trim((string) $this->textEndpoint);
        return $endpoint !== '' ? $endpoint : rtrim($this->baseUrl, '/') . '/chat/completions';
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    private function responsesInput(array $messages): array
    {
        $input = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $input[] = [
                'type' => 'message',
                'role' => (string) ($message['role'] ?? 'user'),
                'content' => $message['content'] ?? '',
            ];
        }
        return $input;
    }

    /** @param array<string, mixed> $data */
    private function extractText(array $data): string
    {
        $content = $data['choices'][0]['message']['content'] ?? $data['output_text'] ?? null;
        if (is_string($content)) {
            return trim($content);
        }
        $parts = [];
        foreach (($data['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['content'] ?? []) as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $parts[] = trim($part['text']);
                }
            }
        }
        return trim(implode("\n", array_filter($parts)));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<float>
     */
    private function floatList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn (mixed $item): float => (float) $item, array_values($value));
    }

    private function requestUid(): string
    {
        return sprintf('%s_%s', $this->providerCode, bin2hex(random_bytes(8)));
    }
}
