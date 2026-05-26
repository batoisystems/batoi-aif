# Framework Independence

Batoi AIF is an independent PHP framework. It can be embedded in any PHP application that can load PHP classes and provide configuration, provider credentials, and caller context.

Batoi RAD support is a first-class integration profile, not a core runtime requirement.

## Core Package

The core package should remain free of hard dependencies on any host application framework. Core code should depend on AIF contracts and value objects, not RAD classes, Laravel classes, Symfony classes, or a specific database abstraction.

Core responsibilities:

- provider abstraction
- governed gateway execution
- prompt governance contracts
- policy decision contracts
- audit contracts
- DTOs and API envelopes
- HTTP provider transport
- generic in-memory implementations

## Integration Profiles

Host-specific behavior belongs in adapters or integration profiles.

Examples:

- `src/Rad/` for Batoi RAD context and permission adapters
- `database/migrations/rad/` for RAD-native MySQL table structure
- future Laravel service providers
- future Symfony bundles
- future queue, cache, vector store, and persistence adapters

## Persistence

The core package should not require MySQL, PostgreSQL, Redis, a queue server, or a vector database. Those systems are valid adapters.

The RAD persistence profile uses MySQL/MariaDB and RAD table conventions:

- `s_aif_*` for RAD-managed AIF system metadata
- `a_aif_*` for workspace/application/operational data
- RAD base columns such as `id`, `uid`, `livestatus`, `space_id`, `createdby`, and timestamps

Non-RAD applications may map the same AIF contracts to their own schema, ORM, document store, or external services.

## Dependency Rule

Core classes may depend on:

- PHP standard library
- AIF contracts and value objects
- minimal PHP extensions required by a concrete core implementation, such as cURL for the bundled HTTP transport

Core classes must not depend on:

- RAD framework classes
- a specific SQL database extension
- framework globals or route state
- application table names

Host integrations may depend on their host framework.
