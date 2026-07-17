<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\DurableQueueAdapterInterface;
use Batoi\Aif\Value\QueueJob;
use InvalidArgumentException;
use PDO;
use PDOException;

final readonly class RadPdoQueueAdapter implements DurableQueueAdapterInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function dispatch(string $jobName, array $payload, array $options = []): string
    {
        $jobName = trim($jobName);
        if ($jobName === '') {
            throw new InvalidArgumentException('Queue job name is required.');
        }

        $uid = sprintf('job_%s', bin2hex(random_bytes(8)));
        $spaceId = $this->spaceId($options['space_id'] ?? 0);
        $idempotencyKey = $options['idempotency_key'] ?? $payload['idempotency_key'] ?? null;
        $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null;
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO a_aif_queue_job (
    uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp,
    a_job_name, a_payload_json, a_options_json, a_status, a_attempt,
    a_max_attempts, a_available_at, a_idempotency_key
) VALUES (
    :uid, '0', 1, 0, :space_id, :createdby, :createstamp,
    :job_name, :payload_json, :options_json, 'queued', 0,
    :max_attempts, :available_at, :idempotency_key
)
SQL);

        try {
            $statement->execute([
                'uid' => $uid,
                'space_id' => $spaceId,
                'createdby' => $this->nullableId($options['user_id'] ?? null),
                'createstamp' => gmdate('Y-m-d H:i:s'),
                'job_name' => $jobName,
                'payload_json' => $this->json($payload),
                'options_json' => $this->json($options),
                'max_attempts' => max(1, (int) ($options['max_attempts'] ?? 3)),
                'available_at' => time() + max(0, (int) ($options['delay_seconds'] ?? 0)),
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (PDOException $exception) {
            if ($idempotencyKey === null) {
                throw $exception;
            }

            $existing = $this->idempotentUid($spaceId, $jobName, $idempotencyKey);
            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        return $uid;
    }

    public function acknowledge(string $jobId): void
    {
        $this->transition($jobId, 'acknowledged');
    }

    public function fail(string $jobId, string $reason, array $metadata = []): void
    {
        $this->transition($jobId, 'failed', $reason, $metadata);
    }

    public function reserve(string $workerId, int $leaseSeconds = 60): ?QueueJob
    {
        if (trim($workerId) === '' || $leaseSeconds < 1) {
            throw new InvalidArgumentException('Queue worker ID and a positive lease duration are required.');
        }

        $now = time();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_queue_job
SET a_status = 'queued', a_lease_owner = NULL, a_lease_expires_at = NULL
WHERE a_status = 'leased' AND a_lease_expires_at <= :now
SQL)->execute(['now' => $now]);
            $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_queue_job
SET a_status = 'dead_lettered', a_failure_reason = 'Maximum attempts exceeded.'
WHERE a_status = 'queued' AND a_attempt >= a_max_attempts
SQL)->execute();

            $suffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE SKIP LOCKED' : '';
            $statement = $this->pdo->prepare(<<<'SQL'
SELECT * FROM a_aif_queue_job
WHERE a_status = 'queued' AND a_available_at <= :now AND a_attempt < a_max_attempts
ORDER BY id ASC LIMIT 1
SQL . $suffix);
            $statement->execute(['now' => $now]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                $this->pdo->commit();

                return null;
            }

            $update = $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_queue_job
SET a_status = 'leased', a_attempt = a_attempt + 1,
    a_lease_owner = :worker_id, a_lease_expires_at = :lease_expires_at
WHERE uid = :uid AND a_status = 'queued'
SQL);
            $update->execute([
                'worker_id' => $workerId,
                'lease_expires_at' => $now + $leaseSeconds,
                'uid' => $row['uid'],
            ]);
            $this->pdo->commit();

            return $this->get((string) $row['uid']);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function release(string $jobId, int $delaySeconds = 0): void
    {
        if ($delaySeconds < 0) {
            throw new InvalidArgumentException('Queue delay cannot be negative.');
        }
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_queue_job
SET a_status = 'queued', a_available_at = :available_at,
    a_lease_owner = NULL, a_lease_expires_at = NULL
WHERE uid = :uid
SQL);
        $statement->execute(['available_at' => time() + $delaySeconds, 'uid' => $jobId]);
        $this->assertUpdated($statement->rowCount(), $jobId);
    }

    public function deadLetter(string $jobId, string $reason, array $metadata = []): void
    {
        $this->transition($jobId, 'dead_lettered', $reason, $metadata);
    }

    public function cancel(string $jobId): void
    {
        $this->transition($jobId, 'cancelled');
    }

    public function get(string $jobId): QueueJob
    {
        $statement = $this->pdo->prepare('SELECT * FROM a_aif_queue_job WHERE uid = :uid LIMIT 1');
        $statement->execute(['uid' => $jobId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException(sprintf('Queue job not found: %s', $jobId));
        }

        $payload = json_decode((string) $row['a_payload_json'], true);
        $options = json_decode((string) $row['a_options_json'], true);

        return new QueueJob(
            id: (string) $row['uid'],
            name: (string) $row['a_job_name'],
            payload: is_array($payload) ? $payload : [],
            options: is_array($options) ? $options : [],
            attempt: (int) $row['a_attempt'],
            leaseOwner: is_string($row['a_lease_owner'] ?? null) ? $row['a_lease_owner'] : null,
            leaseExpiresAt: isset($row['a_lease_expires_at']) ? (float) $row['a_lease_expires_at'] : null,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function transition(string $jobId, string $status, ?string $reason = null, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_queue_job
SET a_status = :status, a_failure_reason = :reason, a_failure_metadata_json = :metadata,
    a_lease_owner = NULL, a_lease_expires_at = NULL
WHERE uid = :uid
SQL);
        $statement->execute([
            'status' => $status,
            'reason' => $reason,
            'metadata' => $this->json($metadata),
            'uid' => $jobId,
        ]);
        $this->assertUpdated($statement->rowCount(), $jobId);
    }

    private function idempotentUid(int $spaceId, string $jobName, string $key): ?string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT uid FROM a_aif_queue_job
WHERE space_id = :space_id AND a_job_name = :job_name AND a_idempotency_key = :idempotency_key
LIMIT 1
SQL);
        $statement->execute(['space_id' => $spaceId, 'job_name' => $jobName, 'idempotency_key' => $key]);
        $uid = $statement->fetchColumn();

        return is_string($uid) ? $uid : null;
    }

    private function assertUpdated(int $count, string $jobId): void
    {
        if ($count !== 1) {
            throw new InvalidArgumentException(sprintf('Queue job not found: %s', $jobId));
        }
    }

    private function spaceId(mixed $value): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
    }

    private function nullableId(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
