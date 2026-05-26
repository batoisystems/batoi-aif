<?php

declare(strict_types=1);

namespace Batoi\Aif\Policy;

enum PolicyAction: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case RequiresReview = 'requires_review';
    case RedactAndContinue = 'redact_and_continue';
}
