<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\CancellationTokenInterface;

final readonly class NeverCancelledToken implements CancellationTokenInterface
{
    public function isCancellationRequested(): bool
    {
        return false;
    }

    public function throwIfCancellationRequested(): void
    {
    }
}
