<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;

interface ExecutionContextResolverInterface
{
    /**
     * @param mixed $request Framework request, session, controller context, CLI context, or other host context.
     */
    public function resolve(mixed $request): ExecutionContext;
}
