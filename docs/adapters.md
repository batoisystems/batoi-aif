# Adapters

Batoi AIF adapters connect the framework-independent core to host frameworks and infrastructure.

Core stays independent. Adapters may depend on Laravel, Symfony, RAD, Redis, RabbitMQ, pgvector, Qdrant, Pinecone, Weaviate, or other infrastructure.

## Adapter Rules

- Adapters must call `AifGateway` for governed execution.
- Adapters must not call providers directly.
- Adapters must not serialize provider secrets into queued payloads.
- Adapters must preserve execution context, trace IDs, policy evidence, and audit correlation.
- Optional adapter dependencies belong outside the core `require` list.

## Current Core Adapter Targets

- `QueueAdapterInterface`
- `VectorStoreInterface`
- `ExecutionContextResolverInterface`
- `PermissionCheckerInterface`

## Implemented Baseline Adapters

- `InMemoryQueueAdapter`
- `InMemoryVectorStore`
- RAD context and role-permission adapters

These are dependency-free and intended for tests, local development, and adapter contract validation.

## Planned Adapters

- Laravel service provider and queue bridge
- Symfony bundle and Messenger bridge
- database queue
- Redis queue
- RabbitMQ queue
- AWS SQS queue
- RAD/MySQL vector baseline
- pgvector
- Qdrant
- Pinecone
- Weaviate
