<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\ProviderCatalogInterface;
use Batoi\Aif\Exception\ProviderNotFoundException;
use Batoi\Aif\Value\ProviderCapability;
use Batoi\Aif\Value\ProviderDefinition;
use PDO;

final readonly class RadPdoProviderCatalog implements ProviderCatalogInterface
{
    public function __construct(private PDO $pdo, private int $workspaceId)
    {
    }

    public function get(string $providerCode): ProviderDefinition
    {
        foreach ($this->all() as $provider) {
            if ($provider->code === $providerCode) {
                return $provider;
            }
        }

        throw ProviderNotFoundException::forCode($providerCode);
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    p.id AS provider_id,
    p.space_id,
    p.s_code,
    p.s_name,
    p.s_provider_type,
    p.s_status,
    p.s_meta_json AS provider_meta_json,
    m.s_code AS model_code,
    m.s_capabilities_json,
    m.s_meta_json AS model_meta_json
FROM s_aif_provider_catalog p
LEFT JOIN s_aif_model_catalog m
    ON m.s_provider_id = p.id
    AND m.space_id = p.space_id
    AND m.livestatus = '0'
    AND m.s_status = 'active'
WHERE p.space_id IN (0, :space_id)
  AND p.livestatus = '0'
  AND p.s_status = 'active'
ORDER BY p.space_id DESC, p.s_code ASC, m.s_code ASC
SQL);
        $statement->execute(['space_id' => $this->workspaceId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<string, ProviderDefinition> $providers */
        $providers = [];

        foreach ($rows as $row) {
            $code = (string) $row['s_code'];
            if (isset($providers[$code])) {
                continue;
            }

            $scopeRows = array_values(array_filter(
                $rows,
                static fn (array $candidate): bool => (string) $candidate['s_code'] === $code
                    && (int) $candidate['space_id'] === (int) $row['space_id'],
            ));
            $models = [];

            foreach ($scopeRows as $modelRow) {
                if (!is_string($modelRow['model_code'] ?? null)) {
                    continue;
                }

                $models[] = new ProviderCapability(
                    provider: $code,
                    model: $modelRow['model_code'],
                    capabilities: $this->stringList($this->jsonValue($modelRow['s_capabilities_json'] ?? null)),
                    metadata: $this->jsonObject($modelRow['model_meta_json'] ?? null),
                );
            }

            $providers[$code] = new ProviderDefinition(
                code: $code,
                name: (string) $row['s_name'],
                type: (string) $row['s_provider_type'],
                status: (string) $row['s_status'],
                models: $models,
                metadata: $this->jsonObject($row['provider_meta_json'] ?? null) + [
                    'source_space_id' => (int) $row['space_id'],
                ],
            );
        }

        return array_values($providers);
    }

    private function jsonValue(mixed $value): mixed
    {
        return is_string($value) && trim($value) !== ''
            ? json_decode($value, true, flags: JSON_THROW_ON_ERROR)
            : [];
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        $decoded = $this->jsonValue($value);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
