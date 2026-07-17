<?php

declare(strict_types=1);

namespace Batoi\Aif\Policy;

enum ExecutionOperation: string
{
    case Infer = 'infer';
    case Stream = 'stream';
    case Embed = 'embed';
    case Moderate = 'moderate';
    case Retrieve = 'retrieve';
    case Tool = 'tool';
}
