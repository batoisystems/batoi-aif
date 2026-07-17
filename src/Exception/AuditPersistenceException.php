<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;
use Throwable;

final class AuditPersistenceException extends RuntimeException
{
    public function __construct(
        public readonly Throwable $auditFailure,
        public readonly ?Throwable $executionFailure = null,
    ) {
        parent::__construct('Required audit evidence could not be persisted.', 0, $auditFailure);
    }
}
