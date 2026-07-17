<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use RuntimeException;

final readonly class RadApiGatewayService
{
    public function __construct(private RadAifService $service, private object $errorHandler)
    {
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $endpoint, array $payload): array
    {
        $definition = is_array($endpoint['definition'] ?? null) ? $endpoint['definition'] : [];
        $prompt = (string) ($definition['prompt_template'] ?? '');
        if ($prompt === '') {
            throw new RuntimeException('AI endpoint missing prompt template.');
        }
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
            }
        }
        try {
            return ['result' => $this->service->completion($prompt)];
        } catch (\Throwable $exception) {
            if (method_exists($this->errorHandler, 'logError')) {
                $this->errorHandler->logError('Batoi AIF endpoint error: ' . $exception->getMessage());
            }
            throw new RuntimeException('AI service unavailable.', 0, $exception);
        }
    }
}
