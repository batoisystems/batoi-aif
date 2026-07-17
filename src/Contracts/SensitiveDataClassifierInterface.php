<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\SensitiveDataClassification;

interface SensitiveDataClassifierInterface
{
    /** @param array<string, mixed> $payload */
    public function classify(array $payload): SensitiveDataClassification;
}
