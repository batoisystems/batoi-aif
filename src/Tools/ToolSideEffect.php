<?php

declare(strict_types=1);

namespace Batoi\Aif\Tools;

enum ToolSideEffect: string
{
    case None = 'none';
    case Read = 'read';
    case Write = 'write';
    case External = 'external';
}
