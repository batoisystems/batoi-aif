<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Value\VectorSearchResult;

interface AccessControlledVectorStoreInterface extends VectorStoreInterface
{
    /** @return list<VectorSearchResult> */
    public function searchGoverned(VectorSearchRequest $request, ExecutionContext $context): array;
}
