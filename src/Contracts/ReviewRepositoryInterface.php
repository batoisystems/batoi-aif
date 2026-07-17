<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ReviewRequest;

interface ReviewRepositoryInterface
{
    public function append(ReviewRequest $review): void;
}
