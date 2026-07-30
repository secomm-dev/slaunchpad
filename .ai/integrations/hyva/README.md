# Hyvä AI Skills Integration (Production Toolkit side)

This directory is the **integration-management metadata** for the official Hyvä
AI skills inside Secomm Production AI Toolkit. It is metadata only — **no skill
bodies live here**. Canonical skill bodies are flat under
`skills-source/dev-skills/magento/hyva-<id>/` (one discoverable skill per direct
child folder; no intermediate `hyva/` nesting).

## Layout

| File | Purpose |
|---|---|
| `upstream-manifest.yaml` | Imported skill inventory + per-file sha256 (drift detection) |
| `provenance.yaml` | Source URL/branch/commit/date, license, ownership boundary, redistribution review |
| `version-lock.yaml` | Pinned upstream commit + integration version (no auto-follow main) |
| `dependency-map.yaml` | Mirrors upstream `requires:` (recursive resolution source) |
| `compatibility-matrix.yaml` | Agent support (claude=skill, codex/copilot=nl-only), profile compatibility, copy-vs-symlink decision |
| `SECOMM_OVERLAY.md` | Thin shared Secomm governance overlay (not a restatement of upstream) |
| `update-policy.md` | Explicit upstream-update lifecycle |
| `retired-secomm-skills/` | Preserved stale Secomm dev-skills replaced by upstream canonical (traceability) |

## Related (outside this dir)

- Canonical bodies: `skills-source/dev-skills/magento/hyva-*/`
- Dev-skill metadata registry: `skills-source/dev-skills/dev-skill-manifest.yaml`
- Selection logic: `generator-rules/skill-selection-rules.md` (Magento Hyvä dev-skills)
- Capability detection: `generator-rules/capability-detection-rules.md` (Hyva classification + feature flags)
- Retrofit: `bin/project-ai-upgrade --capability hyva`
- Validation: `bin/project-ai-validate --check-hyva`

## Upstream snapshot

- Repo: `hyva-themes/hyva-ai-tools` · branch `main`
- Commit: `5f094b6e57e6faab9967e2b031cac9c1cd6c9f93` (pinned)
- License: OSL-3.0 · 12 skills imported

See `provenance.yaml` for the redistribution-review item (not a legal conclusion).
