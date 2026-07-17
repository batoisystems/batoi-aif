<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Value\EmbeddingRequest;
use Batoi\Aif\Value\InferenceRequest;
use RuntimeException;

final class RadAifService
{
    private AifGateway $gateway;
    /** @var array<string, mixed> */
    private array $resolved;

    /** @param array<string, mixed> $radConfig */
    public function __construct(array $radConfig, string $profile = 'general', ?string $quality = null)
    {
        $this->resolved = RadAifConfig::resolve($radConfig, $profile, $quality);
        if ($this->resolved['provider'] !== 'openai') {
            throw new RuntimeException('Batoi AIF provider adapter is not installed: ' . $this->resolved['provider']);
        }
        if (trim((string) $this->resolved['api_key']) === '') {
            throw new RuntimeException('Batoi AIF API key is not configured.');
        }
        $provider = new OpenAICompatibleProvider(
            apiKey: (string) $this->resolved['api_key'],
            providerCode: (string) $this->resolved['provider'],
            defaultTextModel: (string) $this->resolved['model'],
            defaultEmbeddingModel: (string) $this->resolved['embedding_model'],
            timeoutSeconds: max(1, (int) $this->resolved['timeout']),
            textEndpoint: (string) $this->resolved['endpoint'],
        );
        $this->gateway = new AifGateway(
            providers: new InMemoryProviderRegistry(['openai' => $provider]),
            defaultProvider: 'openai',
        );
    }

    /** @param array<string, mixed> $options */
    public function completion(string $prompt, array $options = []): string
    {
        return $this->infer($prompt, [], $options);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): string
    {
        return $this->infer(self::lastText($messages), $messages, $options);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<mixed> $images
     * @param array<string, mixed> $options
     */
    public function visionChat(array $messages, array $images = [], array $options = []): string
    {
        if ($images !== []) {
            $messages[] = ['role' => 'user', 'content' => self::imageParts($images)];
        }
        return $this->chat($messages, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<float>
     */
    public function embed(string $input, array $options = []): array
    {
        return $this->gateway->embed(new EmbeddingRequest(
            input: $input,
            model: isset($options['model']) ? (string) $options['model'] : (string) $this->resolved['embedding_model'],
            metadata: ['rad_profile' => $this->resolved['profile']],
        ))->embedding;
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $options
     */
    private function infer(string $input, array $messages, array $options): string
    {
        $metadata = [
            'messages' => $messages,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->resolved['max_tokens']),
            'temperature' => $options['temperature'] ?? null,
            'rad_profile' => $this->resolved['profile'],
            'rad_quality' => $this->resolved['quality'],
        ];
        return $this->gateway->infer(new InferenceRequest(
            input: $input,
            provider: (string) $this->resolved['provider'],
            model: (string) ($options['model'] ?? $this->resolved['model']),
            metadata: $metadata,
        ))->output;
    }

    /** @param list<array<string, mixed>> $messages */
    private static function lastText(array $messages): string
    {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            $content = $messages[$index]['content'] ?? '';
            if (is_string($content) && trim($content) !== '') {
                return trim($content);
            }
        }
        return '';
    }

    /**
     * @param list<mixed> $images
     * @return list<array<string, mixed>>
     */
    private static function imageParts(array $images): array
    {
        $parts = [];
        foreach ($images as $image) {
            if (!is_string($image)) {
                continue;
            }
            $url = $image;
            if (is_file($image)) {
                $data = file_get_contents($image);
                if ($data === false) {
                    continue;
                }
                $url = 'data:' . (mime_content_type($image) ?: 'image/png') . ';base64,' . base64_encode($data);
            }
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }
        return $parts;
    }
}
