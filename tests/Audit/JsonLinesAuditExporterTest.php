<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Audit;

use Batoi\Aif\Audit\JsonLinesAuditExporter;
use Batoi\Aif\Value\AuditRecord;
use PHPUnit\Framework\TestCase;

final class JsonLinesAuditExporterTest extends TestCase
{
    public function testExporterProducesOneJsonObjectPerImmutableRecord(): void
    {
        $output = (new JsonLinesAuditExporter())->export([
            new AuditRecord('audit_1', 'ok', str_repeat('a', 64)),
            new AuditRecord('audit_2', 'denied', str_repeat('b', 64)),
        ]);
        $lines = array_values(array_filter(explode("\n", $output)));

        self::assertCount(2, $lines);
        self::assertSame('audit_1', json_decode($lines[0], true)['uid']);
        self::assertSame('denied', json_decode($lines[1], true)['status']);
    }
}
