<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Value\AuditRecord;
use DateTimeImmutable;
use PDO;

final readonly class RadPdoAuditLog implements AuditLogInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function append(AuditRecord $record): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO a_aif_call_log (
    uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp,
    a_request_uid, a_provider_request_uid, a_actor_entity_id, a_trace_uid, a_operation,
    a_provider_code, a_model_code, a_prompt_code, a_prompt_version,
    a_policy_decision, a_policy_version, a_policy_evidence_json, a_usage_json,
    a_request_hash, a_response_hash, a_latency_ms, a_status,
    a_error_code, a_error_message, a_metadata_json
) VALUES (
    :uid, '0', 1, 0, :space_id, :createdby, :createstamp,
    :request_uid, :provider_request_uid, :actor_entity_id, :trace_uid, :operation,
    :provider_code, :model_code, :prompt_code, :prompt_version,
    :policy_decision, :policy_version, :policy_evidence_json, :usage_json,
    :request_hash, :response_hash, :latency_ms, :status,
    :error_code, :error_message, :metadata_json
)
SQL);

        $statement->execute([
            'uid' => $record->uid,
            'space_id' => $this->numericId($record->workspaceId, 0),
            'createdby' => $this->numericId($record->userId),
            'createstamp' => $this->dateTime($record->createdAt),
            'request_uid' => $record->metadata['request_uid'] ?? null,
            'provider_request_uid' => $record->providerRequestUid,
            'actor_entity_id' => $this->numericId($record->userId),
            'trace_uid' => $record->traceUid,
            'operation' => $record->operation ?? 'infer',
            'provider_code' => $record->provider,
            'model_code' => $record->model,
            'prompt_code' => $record->promptCode,
            'prompt_version' => $record->promptVersion,
            'policy_decision' => $record->policyDecision['action'] ?? null,
            'policy_version' => $record->policyVersion,
            'policy_evidence_json' => $this->json($record->policyDecision),
            'usage_json' => $this->json($record->usage),
            'request_hash' => $record->requestHash,
            'response_hash' => $record->responseHash,
            'latency_ms' => $record->latencyMs,
            'status' => $record->status,
            'error_code' => $record->errorCode,
            'error_message' => $record->errorMessage === null ? null : substr($record->errorMessage, 0, 512),
            'metadata_json' => $this->json($record->metadata),
        ]);
    }

    private function numericId(?string $value, ?int $default = null): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : $default;
    }

    private function dateTime(?string $value): string
    {
        return $value === null
            ? gmdate('Y-m-d H:i:s')
            : (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
