# Toolkit Version

| Field | Value |
|---|---|
| Project | Secomm Launchpad |
| Toolkit | Secomm Production AI Toolkit (v4) |
| toolkit_version_at_generation | 4.0 |
| generated | 2026-07-14 |
| generator_route | PROJECT_INITIALIZATION |
| target_project | /var/www/html/slaunchpad |
| target_resolved_via | P2 (explicit name) + P4 (cwd) — unique, resolved silently |
| source_shared_core | secomm-production-ai-toolkit/shared-core @ v4 |
| blueprint | .ai/toolkit/PROJECT_AI_BLUEPRINT.md (status: approved, 2026-07-14) |

> A later toolkit sync can compare this version against the current `shared-core/` to detect "behind current". Update via the `update-project-ai-tool` function; log changes in `.ai/CHANGELOG_AI_TOOL.md`.

applied_migrations: [P1A_CANONICAL_RECORDS,P1B_PER_WORK_ITEM_MODE,P1C_PROJECT_MEMORY_INDEX,P1D_RUNTIME_SEPARATION,P1E_CONTEXT_QUOTA_OPTIMIZATION,P1F_GOVERNANCE_DEDUPLICATION]
last_upgraded_at: 2026-07-23
phase1_capabilities:                           # mirror of registry phase1_capabilities (source of truth)
  canonical_records: true
  per_work_item_mode: true
  project_memory: true
  runtime_separation: true
  quota_optimization: true
  governance_deduplication: true
