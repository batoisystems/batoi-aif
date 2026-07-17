<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Rad;

use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Rad\RadPdoAuditLog;
use Batoi\Aif\Rad\RadPdoReviewRepository;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Value\AuditRecord;
use Batoi\Aif\Value\ReviewRequest;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class RadMySqlIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('AIF_MYSQL_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('AIF_MYSQL_DSN is not configured.');
        }

        $this->pdo = new PDO(
            $dsn,
            (string) (getenv('AIF_MYSQL_USER') ?: 'root'),
            (string) (getenv('AIF_MYSQL_PASSWORD') ?: ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    public function testMigrationSupportsImmutableAuditAndSingleUseReview(): void
    {
        $audit = new RadPdoAuditLog($this->pdo);
        $audit->append(new AuditRecord(
            uid: 'mysql_audit_1',
            status: 'ok',
            requestHash: str_repeat('a', 64),
            userId: '10',
            workspaceId: '20',
            operation: 'infer',
        ));
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM a_aif_call_log WHERE uid = 'mysql_audit_1'",
        )->fetchColumn());

        try {
            $this->pdo->exec("UPDATE a_aif_call_log SET a_status = 'error' WHERE uid = 'mysql_audit_1'");
            self::fail('Audit rows must be immutable.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('immutable', $exception->getMessage());
        }

        $reviews = new RadPdoReviewRepository($this->pdo);
        $hash = str_repeat('b', 64);
        $reviews->append(new ReviewRequest(
            uid: 'mysql_review_1',
            operation: ExecutionOperation::Tool,
            requestHash: $hash,
            userId: '10',
            workspaceId: '20',
        ));
        self::assertTrue($reviews->decide('mysql_review_1', '20', ReviewStatus::Approved, '99'));
        self::assertNotNull($reviews->consumeApproved('mysql_review_1', '20', $hash));
        self::assertNull($reviews->consumeApproved('mysql_review_1', '20', $hash));
    }
}
