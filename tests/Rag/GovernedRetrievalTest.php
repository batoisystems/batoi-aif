<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Rag;

use Batoi\Aif\Audit\InMemoryAuditLog;
use Batoi\Aif\Gateway\AifGateway;
use Batoi\Aif\Gateway\RuntimeMode;
use Batoi\Aif\Policy\StaticPolicyEngine;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Rag\GovernedRetrievalService;
use Batoi\Aif\Rag\GovernedRagService;
use Batoi\Aif\Rag\RagIngestionService;
use Batoi\Aif\Rag\TextChunker;
use Batoi\Aif\Tests\Fixtures\RecordingProvider;
use Batoi\Aif\Value\Document;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Vector\InMemoryVectorStore;
use PHPUnit\Framework\TestCase;

final class GovernedRetrievalTest extends TestCase
{
    public function testRetrievalCannotCrossWorkspaceAndCreatesCitations(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = new AifGateway(
            providers: new InMemoryProviderRegistry(['recording' => new RecordingProvider()]),
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
        );
        $vectors = new InMemoryVectorStore();
        $ingestion = new RagIngestionService($gateway, $vectors, new TextChunker(100, 0));
        $spaceOne = new ExecutionContext('10', '1', ['admin']);
        $spaceTwo = new ExecutionContext('20', '2', ['admin']);

        $ingestion->ingest('knowledge', new Document('doc_one', '1', 'Workspace one handbook.'), $spaceOne);
        $ingestion->ingest('knowledge', new Document('doc_two', '2', 'Workspace two secret handbook.'), $spaceTwo);
        $citations = (new GovernedRetrievalService($gateway, $vectors))->search('knowledge', 'handbook', $spaceOne);

        self::assertCount(1, $citations);
        self::assertSame('doc_one', $citations[0]->sourceUid);
        self::assertSame('1', $citations[0]->metadata['space_id']);
        self::assertSame('retrieve', $audit->all()[count($audit->all()) - 1]->operation);
    }

    public function testGenerationAuditCarriesRetrievalEvidenceAndReturnsCitations(): void
    {
        $audit = new InMemoryAuditLog();
        $gateway = new AifGateway(
            providers: new InMemoryProviderRegistry(['recording' => new RecordingProvider()]),
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: $audit,
            runtimeMode: RuntimeMode::Governed,
        );
        $vectors = new InMemoryVectorStore();
        $context = new ExecutionContext('10', '1', ['admin']);
        (new RagIngestionService($gateway, $vectors, new TextChunker(100, 0)))->ingest(
            'knowledge',
            new Document('doc_one', '1', 'Workspace one handbook.'),
            $context,
        );
        $retrieval = new GovernedRetrievalService($gateway, $vectors);

        $result = (new GovernedRagService($gateway, $retrieval))->answer('knowledge', 'handbook', $context);
        $generationAudit = $audit->all()[count($audit->all()) - 1];

        self::assertCount(1, $result->citations);
        self::assertSame('infer', $generationAudit->operation);
        self::assertSame('doc_one', $generationAudit->metadata['retrieval_evidence'][0]['source_uid']);
        self::assertSame('knowledge', $generationAudit->metadata['retrieval_collection']);
    }

    public function testRestrictedChunksRequireMatchingUserOrRole(): void
    {
        $gateway = new AifGateway(
            providers: new InMemoryProviderRegistry(['recording' => new RecordingProvider()]),
            defaultProvider: 'recording',
            policyEngine: new StaticPolicyEngine(),
            auditLog: new InMemoryAuditLog(),
            runtimeMode: RuntimeMode::Governed,
        );
        $vectors = new InMemoryVectorStore();
        $ingestion = new RagIngestionService($gateway, $vectors, new TextChunker(100, 0));
        $admin = new ExecutionContext('10', '1', ['admin']);
        $finance = new ExecutionContext('11', '1', ['finance']);
        $ingestion->ingest(
            'knowledge',
            new Document('restricted', '1', 'Quarterly finance plan.', [
                'acl_visibility' => 'restricted',
                'acl_roles' => ['finance'],
            ]),
            $admin,
        );
        $ingestion->ingest(
            'knowledge',
            new Document('public', '1', 'Public company plan.'),
            $admin,
        );
        $retrieval = new GovernedRetrievalService($gateway, $vectors);

        $adminResults = $retrieval->search('knowledge', 'finance', $admin, topK: 1);
        $financeResults = $retrieval->search('knowledge', 'finance', $finance, topK: 2);

        self::assertCount(1, $adminResults);
        self::assertSame('public', $adminResults[0]->sourceUid);
        self::assertCount(2, $financeResults);
    }
}
