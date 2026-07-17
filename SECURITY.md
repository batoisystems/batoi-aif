# Security Policy

## Supported Versions

Security fixes are applied to the latest supported minor release in the 1.x
line. Users of older minor releases should upgrade before requesting a
backport. Support windows for future major release lines will be documented
here when they are introduced.

## Reporting a Vulnerability

Do not open a public issue for suspected vulnerabilities. Report them privately to Batoi Systems with:

- the affected version or commit;
- reproduction steps;
- expected and observed impact;
- any suggested mitigation;
- whether the report may be acknowledged publicly after remediation.

Do not include live provider credentials, customer prompts, personal data, or production audit records. Use synthetic evidence and revoke any credential that may have been exposed.

## Security Expectations

- Production integrations should use `RuntimeMode::Governed`.
- Provider secrets must be loaded from host configuration or secret storage and must not be committed or queued.
- Public API responses must use stable safe error codes; detailed failures belong only in protected audit storage.
- Review-required actions must remain paused until an authorized, replay-safe approval path is available.
- A security fix is incomplete until denial, audit, redaction, and provider non-invocation regressions are covered by tests.
