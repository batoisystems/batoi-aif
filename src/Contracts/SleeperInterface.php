<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

interface SleeperInterface
{
    public function sleepMilliseconds(int $milliseconds): void;
}
