<?php

declare(strict_types=1);

namespace Batoi\Aif\Observability;

use Batoi\Aif\Contracts\MetricsCollectorInterface;
use Batoi\Aif\Value\MetricEvent;

final class InMemoryMetricsCollector implements MetricsCollectorInterface
{
    /** @var list<MetricEvent> */
    private array $events = [];

    public function record(MetricEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<MetricEvent> */
    public function all(): array
    {
        return $this->events;
    }
}
