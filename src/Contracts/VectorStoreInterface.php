<?php

declare(strict_types=1);

namespace Batoi\Aif\Contracts;

use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Value\VectorSearchResult;

interface VectorStoreInterface
{
    public function upsert(VectorRecord $record): void;

    /**
     * @return list<VectorSearchResult>
     */
    public function search(VectorSearchRequest $request): array;

    public function delete(string $collection, string $id): void;
}
