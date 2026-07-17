<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

final readonly class SensitiveDataClassification
{
    /**
     * @param list<string> $labels
     * @param list<string> $paths
     */
    public function __construct(
        public array $labels = [],
        public array $paths = [],
    ) {
    }

    /** @return array{labels: list<string>, paths: list<string>} */
    public function evidence(): array
    {
        return ['labels' => $this->labels, 'paths' => $this->paths];
    }
}
