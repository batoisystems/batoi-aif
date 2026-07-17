<?php

declare(strict_types=1);

namespace Batoi\Aif\Tools;

use Batoi\Aif\Contracts\ToolInterface;
use InvalidArgumentException;

final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param list<ToolInterface> $tools */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->definition()->code] = $tool;
    }

    public function get(string $code): ToolInterface
    {
        return $this->tools[$code] ?? throw new InvalidArgumentException(sprintf('Tool not found: %s', $code));
    }
}
