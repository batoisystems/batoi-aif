<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use DateTimeImmutable;

interface AuditRetentionInterface
{
    /**
     * Archives immutable evidence before the cutoff; implementations must not silently delete it.
     */
    public function archiveBefore(DateTimeImmutable $cutoff): int;
}
