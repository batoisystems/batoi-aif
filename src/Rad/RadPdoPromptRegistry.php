<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\PromptRegistryInterface;
use Batoi\Aif\Exception\PromptNotFoundException;
use Batoi\Aif\Value\PromptVersion;
use PDO;

final readonly class RadPdoPromptRegistry implements PromptRegistryInterface
{
    public function __construct(private PDO $pdo, private int $workspaceId)
    {
    }

    public function get(string $promptCode, ?string $version = null): PromptVersion
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    p.space_id,
    p.a_code,
    p.a_risk_level,
    v.a_version,
    v.a_template,
    v.a_approval_status,
    v.a_input_schema_json,
    v.a_meta_json
FROM a_aif_prompt p
INNER JOIN a_aif_prompt_version v
    ON v.a_prompt_id = p.id
    AND v.space_id = p.space_id
    AND v.livestatus = '0'
WHERE p.a_code = :code
  AND p.space_id IN (0, :space_id)
  AND p.livestatus = '0'
  AND p.a_status = 'active'
  AND v.a_approval_status = 'approved'
SQL);
        $statement->execute([
            'code' => $promptCode,
            'space_id' => $this->workspaceId,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $version === null || ($row['a_version'] ?? null) === $version,
        ));

        if ($rows === []) {
            throw PromptNotFoundException::forPrompt($promptCode, $version);
        }

        usort($rows, function (array $left, array $right): int {
            $scopeOrder = ((int) $right['space_id']) <=> ((int) $left['space_id']);

            return $scopeOrder !== 0
                ? $scopeOrder
                : version_compare((string) $right['a_version'], (string) $left['a_version']);
        });
        $row = $rows[0];

        return new PromptVersion(
            code: (string) $row['a_code'],
            version: (string) $row['a_version'],
            template: (string) $row['a_template'],
            approvalStatus: (string) $row['a_approval_status'],
            riskLevel: (string) $row['a_risk_level'],
            inputSchema: $this->jsonObject($row['a_input_schema_json'] ?? null),
            metadata: $this->jsonObject($row['a_meta_json'] ?? null) + [
                'source_space_id' => (int) $row['space_id'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
