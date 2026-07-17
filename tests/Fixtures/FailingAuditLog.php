<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\AuditLogInterface;
use Batoi\Aif\Value\AuditRecord;
use RuntimeException;

final class FailingAuditLog implements AuditLogInterface
{
    public int $attempts = 0;

    public function append(AuditRecord $record): void
    {
        $this->attempts++;
        throw new RuntimeException('Database unavailable with credential-must-not-leak.');
    }
}
