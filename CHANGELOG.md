# Changelog

All notable changes are documented here. The project follows semantic versioning.

## [1.0.0] - 2026-07-17

### Added

- Fail-closed governed runtime with operation-aware policy decisions.
- Enforced redaction obligations and audited, single-use approval workflows.
- Stable API validation, safe public errors, and HTTP status mapping.
- Canonical audit hashing, sensitivity classification, correlation fields, export hooks, and immutable RAD persistence.
- Capability-aware provider routing, health evidence, retry/backoff, circuit breaking, cooperative cancellation, bounded responses, and safe rate-limit metadata.
- Prompt input-schema validation, deterministic semantic-version selection, and immutable approved versions.
- Pre- and post-execution evaluation hooks with block, warn, and annotate outcomes.
- Governed RAG ingestion, workspace and ACL filter pushdown, citations, and generation evidence.
- Durable queue contracts and a PDO-backed RAD queue with idempotency, leasing, crash recovery, retry, dead-letter, and cancellation semantics.
- Permission-aware tools, bounded agent execution, and replay-protected review resumption.
- Vendor-neutral operational metrics for latency, usage, cost, policy decisions, retries, provider health, and audit failures.
- Isolated Laravel and Symfony integration packages.
- PHPUnit, PHPStan, PHP_CodeSniffer, PHP 8.3/8.4 CI, MySQL 8 migration tests, and release-policy documentation.

### Changed

- Every governed execution terminal state now attempts exactly one audit record.
- Prompt preparation and provider routing execute inside the audited boundary.
- Provider errors classify retryability and redact common credential formats.
- OpenAI-compatible streaming is explicitly reported as unsupported rather than emulated with buffered inference.
- RAD call-log and review states align with core governance outcomes.

[1.0.0]: https://github.com/batoisystems/batoi-aif/releases/tag/v1.0.0
