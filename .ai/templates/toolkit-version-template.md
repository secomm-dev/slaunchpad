---
# Toolkit / schema version + Phase 1 propagation metadata (B4).
# Populated on fresh generation; `last_upgraded_at` + `applied_migrations` updated by
# `bin/project-ai-upgrade` (B3 migration engine). Project-tracked (durable). Do not hand-edit
# `applied_migrations` / `last_upgraded_at` — the upgrade engine owns them.
toolkit_version: "{toolkit_version}"          # from registry project_toolkit_version
schema_version: 1                              # registry schema_version
generator_commit: "{generator_commit}"         # toolkit git sha at generation
generated_at: "{YYYY-MM-DDTHH:MM:SSZ}"         # fresh-generation timestamp
last_upgraded_at: "-"                          # "-" until first project-ai-upgrade; ISO8601 after
applied_migrations: []                         # B3 marker store — P1A..P1F + P2A stamped as applied
phase1_capabilities:                           # mirror of registry phase1_capabilities (source of truth)
  canonical_records: true
  per_work_item_mode: true
  project_memory: true
  runtime_separation: true
  quota_optimization: true
  governance_deduplication: true
phase2_capabilities:                           # mirror of registry phase2_capabilities (P2A identity model)
  project_code: true
  collision_safe_ids: true
  parent_relationship: true
  display_names: true
  legacy_aliases: true
---

# Toolkit Version

| Field | Value |
|---|---|
| Project | {project_name} |
| Toolkit | Secomm Production AI Toolkit (v4) |
| toolkit_version | {toolkit_version} |
| schema_version | 1 |
| generator_commit | {generator_commit} |
| generated_at | {generated_at} |
| last_upgraded_at | {last_upgraded_at or "-"} |
| applied_migrations | {applied_migrations or "none"} |
| project_code | {.ai/toolkit/project.yaml → project.code (P2A; authoritative — do not re-infer)} |
| generator_route | PROJECT_INITIALIZATION |
| target_project | {resolved_target} |
| target_resolved_via | {resolved_via} |
| source_shared_core | secomm-production-ai-toolkit/shared-core @ v4 |
| blueprint | .ai/toolkit/PROJECT_AI_BLUEPRINT.md (status: {blueprint_status}) |

> **Phase 1 propagation (B3/B4):** `applied_migrations` records which Phase 1 migrations have been
> applied to this project; `bin/project-ai-upgrade` applies only missing ones (idempotent).
> `phase1_capabilities` mirrors the toolkit registry. A later toolkit sync compares `toolkit_version`
> + `generator_commit` against current `shared-core/` to detect "behind current"; update via
> `update-project-ai-tool` / `bin/project-ai-upgrade`; log in `.ai/CHANGELOG_AI_TOOL.md`.
