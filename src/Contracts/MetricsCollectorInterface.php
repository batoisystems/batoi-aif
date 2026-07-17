<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\MetricEvent;

interface MetricsCollectorInterface
{
    public function record(MetricEvent $event): void;
}
