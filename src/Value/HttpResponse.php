<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = [],
    ) {
    }
}
