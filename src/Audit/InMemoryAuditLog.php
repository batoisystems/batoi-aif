<?php

declare(strict_types=1);

namespace Batoi\Aif\Audit;

use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Value\AuditRecord;

final class InMemoryAuditLog implements AuditLogInterface
{
    /**
     * @var list<AuditRecord>
     */
    private array $records = [];

    public function append(AuditRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return list<AuditRecord>
     */
    public function all(): array
    {
        return $this->records;
    }
}
