<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\AuditRecord;

interface AuditLogInterface
{
    public function append(AuditRecord $record): void;
}
