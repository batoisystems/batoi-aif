<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

interface CancellationTokenInterface
{
    public function isCancellationRequested(): bool;

    public function throwIfCancellationRequested(): void;
}
