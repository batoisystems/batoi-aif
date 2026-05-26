<?php

declare(strict_types=1);

namespace Batoi\Aif\Vector;

use Batoi\Aif\Contracts\VectorStoreInterface;
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Value\VectorSearchResult;
use InvalidArgumentException;

final class InMemoryVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, array<string, VectorRecord>>
     */
    private array $records = [];

    public function upsert(VectorRecord $record): void
    {
        $this->records[$record->collection][$record->id] = $record;
    }

    public function search(VectorSearchRequest $request): array
    {
        $query = $request->normalizedVector();
        $results = [];

        foreach ($this->records[$request->collection] ?? [] as $record) {
            if (!$this->matchesFilters($record, $request->filters)) {
                continue;
            }

            $score = $this->cosineSimilarity($query, $record->normalizedVector());

            if ($score < $request->minScore) {
                continue;
            }

            $results[] = new VectorSearchResult($record, $score);
        }

        usort(
            $results,
            static fn (VectorSearchResult $left, VectorSearchResult $right): int => $right->score <=> $left->score,
        );

        return array_slice($results, 0, $request->topK);
    }

    public function delete(string $collection, string $id): void
    {
        unset($this->records[$collection][$id]);
    }

    /**
     * @return list<VectorRecord>
     */
    public function all(?string $collection = null): array
    {
        if ($collection !== null) {
            return array_values($this->records[$collection] ?? []);
        }

        $records = [];

        foreach ($this->records as $collectionRecords) {
            foreach ($collectionRecords as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function matchesFilters(VectorRecord $record, array $filters): bool
    {
        foreach ($filters as $key => $expected) {
            if (!array_key_exists($key, $record->metadata)) {
                return false;
            }

            $actual = $record->metadata[$key];

            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) {
                    return false;
                }

                continue;
            }

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        if (count($left) !== count($right)) {
            throw new InvalidArgumentException('Vector dimensions must match for similarity search.');
        }

        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            $dotProduct += $leftValue * $rightValue;
            $leftMagnitude += $leftValue * $leftValue;
            $rightMagnitude += $rightValue * $rightValue;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }
}
