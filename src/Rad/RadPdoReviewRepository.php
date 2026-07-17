<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\ReviewWorkflowRepositoryInterface;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Value\ReviewRequest;
use PDO;

final readonly class RadPdoReviewRepository implements ReviewWorkflowRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function append(ReviewRequest $review): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO a_aif_review (
    uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp,
    a_operation, a_request_hash, a_policy_evidence_json, a_status
) VALUES (
    :uid, '0', 1, 0, :space_id, :createdby, :createstamp,
    :operation, :request_hash, :policy_evidence_json, :status
)
SQL);

        $statement->execute([
            'uid' => $review->uid,
            'space_id' => ctype_digit($review->workspaceId) ? (int) $review->workspaceId : 0,
            'createdby' => ctype_digit($review->userId) ? (int) $review->userId : null,
            'createstamp' => gmdate('Y-m-d H:i:s'),
            'operation' => $review->operation->value,
            'request_hash' => $review->requestHash,
            'policy_evidence_json' => json_encode(
                $review->policyEvidence,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'status' => $review->status->value,
        ]);
    }

    public function get(string $uid, string $workspaceId): ?ReviewRequest
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT * FROM a_aif_review
WHERE uid = :uid AND space_id = :space_id
LIMIT 1
SQL);
        $statement->execute(['uid' => $uid, 'space_id' => $this->spaceId($workspaceId)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function decide(
        string $uid,
        string $workspaceId,
        ReviewStatus $status,
        string $decidedBy,
        ?string $notes = null,
    ): bool {
        if (!in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            return false;
        }

        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_review
SET a_status = :status, a_decidedby = :decidedby, a_decidedstamp = :decidedstamp,
    a_decision_notes = :decision_notes
WHERE uid = :uid AND space_id = :space_id AND a_status = 'pending'
SQL);
        $statement->execute([
            'status' => $status->value,
            'decidedby' => ctype_digit($decidedBy) ? (int) $decidedBy : null,
            'decidedstamp' => gmdate('Y-m-d H:i:s'),
            'decision_notes' => $notes,
            'uid' => $uid,
            'space_id' => $this->spaceId($workspaceId),
        ]);

        return $statement->rowCount() === 1;
    }

    public function consumeApproved(string $uid, string $workspaceId, string $requestHash): ?ReviewRequest
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE a_aif_review
SET a_status = 'consumed'
WHERE uid = :uid AND space_id = :space_id AND a_request_hash = :request_hash AND a_status = 'approved'
SQL);
        $statement->execute([
            'uid' => $uid,
            'space_id' => $this->spaceId($workspaceId),
            'request_hash' => $requestHash,
        ]);

        return $statement->rowCount() === 1 ? $this->get($uid, $workspaceId) : null;
    }

    private function spaceId(string $workspaceId): int
    {
        return ctype_digit($workspaceId) ? (int) $workspaceId : 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ReviewRequest
    {
        $evidence = json_decode((string) ($row['a_policy_evidence_json'] ?? '{}'), true);

        return new ReviewRequest(
            uid: (string) $row['uid'],
            operation: ExecutionOperation::from((string) $row['a_operation']),
            requestHash: (string) $row['a_request_hash'],
            userId: (string) ($row['createdby'] ?? ''),
            workspaceId: (string) $row['space_id'],
            policyEvidence: is_array($evidence) ? $evidence : [],
            status: ReviewStatus::from((string) $row['a_status']),
            decidedBy: isset($row['a_decidedby']) ? (string) $row['a_decidedby'] : null,
            decidedAt: isset($row['a_decidedstamp']) ? (string) $row['a_decidedstamp'] : null,
            decisionNotes: isset($row['a_decision_notes']) ? (string) $row['a_decision_notes'] : null,
        );
    }
}
