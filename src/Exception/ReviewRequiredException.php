<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ReviewRequiredException extends RuntimeException
{
    public function __construct(public readonly string $reviewUid)
    {
        parent::__construct(sprintf('Execution requires review: %s', $reviewUid));
    }
}
