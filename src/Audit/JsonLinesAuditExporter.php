<?php

declare(strict_types=1);

namespace Batoi\Aif\Audit;

use Batoi\Aif\Contracts\AuditExporterInterface;
use Batoi\Aif\Value\AuditRecord;

final readonly class JsonLinesAuditExporter implements AuditExporterInterface
{
    public function export(iterable $records): string
    {
        $lines = [];
        foreach ($records as $record) {
            $lines[] = json_encode(get_object_vars($record), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }
}
