<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

final readonly class RadCodeAssistService
{
    public function __construct(private RadAifService $service, private object $errorHandler)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    public function suggest(string $context, string $variant = 'generic', array $metadata = []): array
    {
        $context = trim($context);
        if ($context === '') {
            return ['error' => 'Provide some code context before requesting suggestions.'];
        }
        $context = strlen($context) > 6000 ? substr($context, -6000) : $context;
        $prompt = 'You are a senior Batoi RAD engineer. Return only the next raw code lines, without fences or explanation.';
        $prompt .= "\nVariant: " . $variant . "\nContext:\n" . $context;
        foreach ($metadata as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $prompt .= "\n" . strtoupper((string) $key) . ': ' . $value;
            }
        }
        try {
            $response = $this->service->completion($prompt);
        } catch (\Throwable $exception) {
            if (method_exists($this->errorHandler, 'logError')) {
                $this->errorHandler->logError('Batoi AIF suggestion error: ' . $exception->getMessage());
            }
            return ['error' => 'AI service is currently unavailable.'];
        }
        $response = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $response) ?? $response;
        $response = preg_replace('/```$/', '', $response) ?? $response;
        return trim($response) === '' ? ['error' => 'AI returned an empty response.'] : ['suggestion' => ltrim($response)];
    }
}
