<?php

declare(strict_types=1);

namespace Batoi\Aif\Gateway;

enum RuntimeMode: string
{
    case Development = 'development';
    case Governed = 'governed';
}
