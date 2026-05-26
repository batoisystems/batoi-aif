<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use Batoi\Aif\Value\PolicyDecision;
use RuntimeException;

final class PolicyDeniedException extends RuntimeException
{
    public static function fromDecision(PolicyDecision $decision): self
    {
        return new self(implode('; ', $decision->reasons));
    }
}
