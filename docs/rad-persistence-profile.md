# RAD Persistence Profile

Batoi AIF uses a RAD-native persistence profile when installed inside a Batoi RAD App.

## Table Families

Use `s_aif_*` for AIF system/framework metadata managed centrally by RAD:

- provider catalog
- model catalog
- capability definitions
- policy templates
- evaluator templates
- shared schemas and runtime defaults

Use `a_aif_*` for workspace, user, application, and operational data:

- prompts and prompt versions
- workspace policies and rules
- call logs and evidence
- evaluation results
- reviews, workflows, memory, and embeddings

## Required Base Columns

Every AIF RAD table must start with the standard RAD system columns:

```sql
id bigint(20) NOT NULL AUTO_INCREMENT,
uid char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
livestatus enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
versioncode int(11) DEFAULT NULL,
wf_status int(11) NOT NULL DEFAULT '0',
space_id bigint(20) NOT NULL DEFAULT '0',
createdby bigint(20) DEFAULT NULL,
createstamp datetime DEFAULT NULL,
updatedby bigint(20) DEFAULT NULL,
updatestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

Every table should include:

```sql
PRIMARY KEY (id),
UNIQUE KEY uid (uid)
```

## Column Prefixes

- `s_aif_*` table-specific fields use `s_`.
- `a_aif_*` table-specific fields use `a_`.
- Use `space_id = 0` for global defaults and workspace `space_id` values for overrides.
- Use `_json` suffixes for structured longtext payloads.

## Audit Immutability

Evidence tables such as `a_aif_call_log` must be append-only. The RAD migration uses update/delete triggers to enforce immutability at the database level.
