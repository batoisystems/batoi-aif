<?php

declare(strict_types=1);

namespace Batoi\Aif\Audit;

use Batoi\Aif\Contracts\SensitiveDataClassifierInterface;
use Batoi\Aif\Value\SensitiveDataClassification;

final readonly class KeyNameSensitiveDataClassifier implements SensitiveDataClassifierInterface
{
    /** @param array<string, string> $patterns Pattern fragment to evidence label. */
    public function __construct(
        private array $patterns = [
            'password' => 'credential',
            'secret' => 'credential',
            'token' => 'credential',
            'authorization' => 'credential',
            'api_key' => 'credential',
            'email' => 'personal_data',
            'phone' => 'personal_data',
            'ssn' => 'regulated_identifier',
            'tax_id' => 'regulated_identifier',
        ],
    ) {
    }

    public function classify(array $payload): SensitiveDataClassification
    {
        $labels = [];
        $paths = [];
        $this->walk($payload, '', $labels, $paths);

        return new SensitiveDataClassification(array_values(array_unique($labels)), $paths);
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> $labels
     * @param list<string> $paths
     */
    private function walk(array $payload, string $parent, array &$labels, array &$paths): void
    {
        foreach ($payload as $key => $value) {
            $path = $parent === '' ? (string) $key : sprintf('%s.%s', $parent, (string) $key);
            $normalized = strtolower((string) $key);

            foreach ($this->patterns as $pattern => $label) {
                if (str_contains($normalized, $pattern)) {
                    $labels[] = $label;
                    $paths[] = $path;
                    break;
                }
            }

            if (is_array($value)) {
                $this->walk($value, $path, $labels, $paths);
            }
        }
    }
}
