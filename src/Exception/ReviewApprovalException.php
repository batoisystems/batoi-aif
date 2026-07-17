<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use RuntimeException;

final class ReviewApprovalException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The review approval is invalid, unavailable, or already consumed.');
    }
}
