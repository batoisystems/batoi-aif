# UIF Admin and Review Contract

Batoi UIF should render AIF administration and review workflows from server-provided records. UIF must not own policy decisions, provider routing, prompt approval rules, or audit creation.

## Primary Screens

- Provider catalog: list, status, capabilities, configuration schema.
- Model catalog: provider, model family, capabilities, context window, price metadata.
- Prompt registry: prompt list, versions, approval status, risk level, tags.
- Policy registry: workspace policies, rules, status, and decision preview.
- Audit log: immutable execution evidence, policy decision, provider/model, prompt version, usage, error state.
- Review queue: requests requiring approval, approve/reject actions, evidence summary.
- Evaluation results: pass/warn/fail/error, score, evaluator details.

## Common List Columns

Use these columns consistently where applicable:

- `uid`
- `space_id`
- `livestatus`
- `a_status` or `s_status`
- title/name/code
- created by/time
- updated by/time

## Status Presentation

Recommended status groups:

- Lifecycle: inactive, active, archived, suspended from `livestatus`.
- Prompt approval: draft, pending review, approved, deprecated, rejected.
- Policy decision: allow, deny, requires review, redact and continue.
- Execution: ok, error, denied, requires review.
- Evaluation: pass, warn, fail, error.

## Review Actions

Review actions should call server endpoints. UIF should only submit the reviewer decision and notes.

```json
{
  "decision": "approve",
  "notes": "Approved for this workspace request."
}
```

The server must write the audit/review event and decide whether execution can continue.

## Filters

Every high-volume screen should support:

- workspace
- status
- provider
- model
- prompt
- actor/entity
- date range
- text/code search

## Design Boundary

UIF is the presentation layer. AIF remains the authority for:

- policy checks
- prompt rendering and approval enforcement
- provider/model routing
- audit/evidence immutability
- review continuation after approval
