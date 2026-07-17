<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\AuditRecord;

interface AuditExporterInterface
{
    /** @param iterable<AuditRecord> $records */
    public function export(iterable $records): string;
}
