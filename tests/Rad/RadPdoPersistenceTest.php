<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Rad;

use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Policy\PolicyAction;
use Batoi\Aif\Rad\RadPdoAuditLog;
use Batoi\Aif\Rad\RadPdoPromptRegistry;
use Batoi\Aif\Rad\RadPdoPolicyEngine;
use Batoi\Aif\Rad\RadPdoProviderCatalog;
use Batoi\Aif\Rad\RadPdoReviewRepository;
use Batoi\Aif\Rad\RadPdoQueueAdapter;
use Batoi\Aif\Review\ReviewStatus;
use Batoi\Aif\Value\AuditRecord;
use Batoi\Aif\Value\ReviewRequest;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicySubject;
use PDO;
use PHPUnit\Framework\TestCase;

final class RadPdoPersistenceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_call_log (
    uid TEXT PRIMARY KEY, livestatus TEXT, versioncode INTEGER, wf_status INTEGER,
    space_id INTEGER, createdby INTEGER, createstamp TEXT, a_request_uid TEXT,
    a_provider_request_uid TEXT,
    a_actor_entity_id INTEGER, a_trace_uid TEXT, a_operation TEXT,
    a_provider_code TEXT, a_model_code TEXT, a_prompt_code TEXT, a_prompt_version TEXT,
    a_policy_decision TEXT, a_policy_version TEXT, a_policy_evidence_json TEXT, a_usage_json TEXT,
    a_request_hash TEXT, a_response_hash TEXT, a_latency_ms INTEGER, a_status TEXT,
    a_error_code TEXT, a_error_message TEXT, a_metadata_json TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_review (
    uid TEXT PRIMARY KEY, livestatus TEXT, versioncode INTEGER, wf_status INTEGER,
    space_id INTEGER, createdby INTEGER, createstamp TEXT, a_operation TEXT,
    a_request_hash TEXT, a_policy_evidence_json TEXT, a_status TEXT,
    a_decidedby INTEGER, a_decidedstamp TEXT, a_decision_notes TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_queue_job (
    id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, livestatus TEXT, versioncode INTEGER,
    wf_status INTEGER, space_id INTEGER, createdby INTEGER, createstamp TEXT,
    a_job_name TEXT, a_payload_json TEXT, a_options_json TEXT, a_status TEXT,
    a_attempt INTEGER, a_max_attempts INTEGER, a_available_at INTEGER,
    a_lease_owner TEXT, a_lease_expires_at INTEGER, a_failure_reason TEXT,
    a_failure_metadata_json TEXT, a_idempotency_key TEXT,
    UNIQUE(space_id, a_job_name, a_idempotency_key)
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_prompt (
    id INTEGER PRIMARY KEY, space_id INTEGER, livestatus TEXT,
    a_code TEXT, a_risk_level TEXT, a_status TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_prompt_version (
    id INTEGER PRIMARY KEY, space_id INTEGER, livestatus TEXT, a_prompt_id INTEGER,
    a_version TEXT, a_template TEXT, a_approval_status TEXT,
    a_input_schema_json TEXT, a_meta_json TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_policy (
    id INTEGER PRIMARY KEY, uid TEXT, versioncode INTEGER, space_id INTEGER,
    livestatus TEXT, a_status TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE a_aif_policy_rule (
    id INTEGER PRIMARY KEY, uid TEXT, space_id INTEGER, livestatus TEXT,
    a_policy_id INTEGER, a_rule_key TEXT, a_action TEXT, a_match_json TEXT,
    a_weight INTEGER, a_is_active TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE s_aif_provider_catalog (
    id INTEGER PRIMARY KEY, space_id INTEGER, livestatus TEXT, s_code TEXT,
    s_name TEXT, s_provider_type TEXT, s_status TEXT, s_meta_json TEXT
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE s_aif_model_catalog (
    id INTEGER PRIMARY KEY, space_id INTEGER, livestatus TEXT, s_provider_id INTEGER,
    s_code TEXT, s_status TEXT, s_capabilities_json TEXT, s_meta_json TEXT
)
SQL);
    }

    public function testAuditRecordIsPersistedWithCorrelationAndEvidence(): void
    {
        (new RadPdoAuditLog($this->pdo))->append(new AuditRecord(
            uid: 'audit_1',
            status: 'ok',
            requestHash: str_repeat('a', 64),
            responseHash: str_repeat('b', 64),
            provider: 'openai',
            model: 'model-1',
            policyDecision: ['action' => 'allow', 'evidence' => ['rule' => 'r1']],
            usage: ['input_tokens' => 4],
            metadata: ['request_uid' => 'request_1'],
            userId: '10',
            workspaceId: '20',
            traceUid: 'trace_1',
            operation: 'infer',
            createdAt: '2026-07-16T12:00:00+00:00',
            latencyMs: 25,
            providerRequestUid: 'provider_request_1',
            policyVersion: 'policy-v1',
        ));

        $row = $this->pdo->query('SELECT * FROM a_aif_call_log')->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('trace_1', $row['a_trace_uid']);
        self::assertSame('allow', $row['a_policy_decision']);
        self::assertSame('provider_request_1', $row['a_provider_request_uid']);
        self::assertSame('policy-v1', $row['a_policy_version']);
        self::assertSame('20', (string) $row['space_id']);
        self::assertSame(['input_tokens' => 4], json_decode($row['a_usage_json'], true));
    }

    public function testReviewRequestIsPersistedAsPending(): void
    {
        (new RadPdoReviewRepository($this->pdo))->append(new ReviewRequest(
            uid: 'review_1',
            operation: ExecutionOperation::Tool,
            requestHash: str_repeat('c', 64),
            userId: '10',
            workspaceId: '20',
            policyEvidence: ['reasons' => ['approval_required']],
        ));

        $row = $this->pdo->query('SELECT * FROM a_aif_review')->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('tool', $row['a_operation']);
        self::assertSame('pending', $row['a_status']);
        self::assertSame(['reasons' => ['approval_required']], json_decode($row['a_policy_evidence_json'], true));
    }

    public function testApprovedReviewIsConsumedOnceAndBoundToRequestHash(): void
    {
        $repository = new RadPdoReviewRepository($this->pdo);
        $hash = str_repeat('d', 64);
        $repository->append(new ReviewRequest(
            uid: 'review_once',
            operation: ExecutionOperation::Tool,
            requestHash: $hash,
            userId: '10',
            workspaceId: '20',
        ));

        self::assertTrue($repository->decide('review_once', '20', ReviewStatus::Approved, '99', 'Checked'));
        self::assertNull($repository->consumeApproved('review_once', '20', str_repeat('e', 64)));

        $consumed = $repository->consumeApproved('review_once', '20', $hash);
        self::assertNotNull($consumed);
        self::assertSame(ReviewStatus::Consumed, $consumed->status);
        self::assertSame('99', $consumed->decidedBy);
        self::assertNull($repository->consumeApproved('review_once', '20', $hash));
    }

    public function testPdoQueuePersistsIdempotencyAndRecoversExpiredLease(): void
    {
        $queue = new RadPdoQueueAdapter($this->pdo);
        $options = ['space_id' => 20, 'idempotency_key' => 'once', 'max_attempts' => 2];
        $uid = $queue->dispatch('aif.embed', ['trace_uid' => 'trace_1'], $options);

        self::assertSame($uid, $queue->dispatch('aif.embed', ['trace_uid' => 'trace_1'], $options));
        $firstLease = $queue->reserve('worker_1', 60);
        self::assertNotNull($firstLease);
        self::assertSame(1, $firstLease->attempt);

        $this->pdo->exec("UPDATE a_aif_queue_job SET a_lease_expires_at = 0 WHERE uid = '" . $uid . "'");
        $recovered = $queue->reserve('worker_2', 60);
        self::assertNotNull($recovered);
        self::assertSame(2, $recovered->attempt);
        self::assertSame('worker_2', $recovered->leaseOwner);

        $queue->acknowledge($uid);
        self::assertNull($queue->reserve('worker_3'));
    }

    public function testPromptRegistryPrefersWorkspaceAndApprovedSemanticVersion(): void
    {
        $this->pdo->exec(<<<'SQL'
INSERT INTO a_aif_prompt VALUES
    (1, 0, '0', 'summary', 'low', 'active'),
    (2, 20, '0', 'summary', 'high', 'active');
INSERT INTO a_aif_prompt_version VALUES
    (1, 0, '0', 1, '2.0.0', 'Global {{text}}', 'approved', '{"required":["text"]}', '{}'),
    (2, 20, '0', 2, '1.9.0', 'Workspace old {{text}}', 'approved', '{}', '{}'),
    (3, 20, '0', 2, '1.10.0', 'Workspace new {{text}}', 'approved', '{}', '{"owner":"team"}'),
    (4, 20, '0', 2, '2.0.0', 'Workspace draft {{text}}', 'draft', '{}', '{}');
SQL);

        $prompt = (new RadPdoPromptRegistry($this->pdo, 20))->get('summary');

        self::assertSame('1.10.0', $prompt->version);
        self::assertSame('Workspace new {{text}}', $prompt->template);
        self::assertSame('high', $prompt->riskLevel);
        self::assertSame(20, $prompt->metadata['source_space_id']);
    }

    public function testPolicyEngineUsesWorkspaceRuleAndDefaultsToDeny(): void
    {
        $this->pdo->exec(<<<'SQL'
INSERT INTO a_aif_policy VALUES (1, 'policy_1', 3, 20, '0', 'active');
INSERT INTO a_aif_policy_rule VALUES
    (1, 'rule_1', 20, '0', 1, 'admin_infer', 'allow',
     '{"operations":["infer"],"roles_any":["admin"],"providers":["openai"]}', 100, '1');
SQL);
        $engine = new RadPdoPolicyEngine($this->pdo);
        $context = new ExecutionContext('10', '20', ['admin']);

        $allowed = $engine->decideForOperation(
            $context,
            new PolicySubject(ExecutionOperation::Infer, new InferenceRequest('Hello', provider: 'openai')),
        );
        $denied = $engine->decideForOperation(
            $context,
            new PolicySubject(ExecutionOperation::Embed, new InferenceRequest('Hello', provider: 'openai')),
        );

        self::assertSame(PolicyAction::Allow, $allowed->action);
        self::assertSame('3', $allowed->evidence['policy_version']);
        self::assertSame(PolicyAction::Deny, $denied->action);
        self::assertSame(['no_policy_rule_matched'], $denied->reasons);
    }

    public function testProviderCatalogReturnsWorkspaceMetadataWithoutSecrets(): void
    {
        $this->pdo->exec(<<<'SQL'
INSERT INTO s_aif_provider_catalog VALUES
    (1, 0, '0', 'openai', 'Global OpenAI', 'openai_compatible', 'active', '{}'),
    (2, 20, '0', 'openai', 'Workspace OpenAI', 'openai_compatible', 'active', '{"region":"in"}');
INSERT INTO s_aif_model_catalog VALUES
    (1, 20, '0', 2, 'model-1', 'active', '["text","embedding"]', '{"context_window":1000}');
SQL);

        $provider = (new RadPdoProviderCatalog($this->pdo, 20))->get('openai');

        self::assertSame('Workspace OpenAI', $provider->name);
        self::assertSame('in', $provider->metadata['region']);
        self::assertSame(20, $provider->metadata['source_space_id']);
        self::assertTrue($provider->models[0]->supports('embedding'));
    }
}
