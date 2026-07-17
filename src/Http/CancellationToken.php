<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\CancellationTokenInterface;
use Batoi\Aif\Exception\ExecutionCancelledException;

final class CancellationToken implements CancellationTokenInterface
{
    private bool $cancelled = false;

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancellationRequested(): bool
    {
        return $this->cancelled;
    }

    public function throwIfCancellationRequested(): void
    {
        if ($this->cancelled) {
            throw new ExecutionCancelledException();
        }
    }
}
