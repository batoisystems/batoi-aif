<?php

declare(strict_types=1);

namespace Batoi\Aif\Exception;

use InvalidArgumentException;

final class RequestValidationException extends InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
