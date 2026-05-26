# Vector Store Adapters

Vector store adapters let AIF support governed RAG without coupling core to a specific vector database.

## Contract

```php
use Batoi\Aif\Contracts\VectorStoreInterface;
```

Required methods:

- `upsert(VectorRecord $record): void`
- `search(VectorSearchRequest $request): array`
- `delete(string $collection, string $id): void`

## Baseline Adapter

```php
use Batoi\Aif\Value\VectorRecord;
use Batoi\Aif\Value\VectorSearchRequest;
use Batoi\Aif\Vector\InMemoryVectorStore;

$store = new InMemoryVectorStore();
$store->upsert(new VectorRecord(
    collection: 'tickets',
    id: 'ticket_1_chunk_1',
    vector: [1.0, 0.0],
    content: 'Printer is offline.',
    metadata: ['space_id' => '10', 'source_uid' => 'ticket_1']
));

$results = $store->search(new VectorSearchRequest(
    collection: 'tickets',
    vector: [0.9, 0.1],
    topK: 5,
    filters: ['space_id' => '10']
));
```

## Required Metadata

Production vector records should carry:

- collection
- record ID
- vector
- content hash
- source URI or document UID
- chunk index
- workspace or tenant ID
- access-control metadata
- embedding provider and model
- created timestamp

## Search Rules

Vector search should support:

- top K
- score threshold
- metadata filters
- workspace/tenant filter
- source type filter
- access-control prefilter or postfilter
- citation metadata in results

## Security Rules

- Never return unauthorized chunks.
- Do not mix tenant/workspace vectors unless policy explicitly allows it.
- Store source/citation metadata separately from secrets.
- Audit retrieval context used for generation.
