<?php

declare(strict_types=1);

namespace Batoi\Aif\Review;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Consumed = 'consumed';
}
