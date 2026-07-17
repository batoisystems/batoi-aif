<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\SleeperInterface;

final readonly class SystemSleeper implements SleeperInterface
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
