<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Fixtures;

use Batoi\Aif\Contracts\SleeperInterface;

final class RecordingSleeper implements SleeperInterface
{
    /** @var list<int> */
    public array $delays = [];

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}
