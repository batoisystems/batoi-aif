<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\PromptVersion;

interface PromptRegistryInterface
{
    public function get(string $promptCode, ?string $version = null): PromptVersion;
}
