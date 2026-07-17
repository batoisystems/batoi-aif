<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class ProviderRoute
{
    public function __construct(
        public string $provider,
        public ?string $model,
        public string $reason,
    ) {
    }
}
