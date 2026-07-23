# Audits Index

> **Language:** English (selection meta). Generated `.ai/audits/{type}/` is sourced from `workflow-guides/audit-workflows.md` (Vietnamese) + this selection logic.

The toolkit's audit content lives in [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md) (12 audits after the v4 additions: Architecture, Code Quality, Security, Performance, Magento, Shopify, **Hyva**, **API**, Deployment, **Documentation**, Estimation, AI Output). This index defines **which audits a generated project includes** based on platform/project type.

> Run by the Project Auditor agent (`shared-core/agents/project-auditor.md`). Each audit: objective / inputs / checklist (cross-ref skills+checklists) / severity (S0–S3) / evidence / output / next-actions — already the standard format in `audit-workflows.md`.

---

## Base audits (every project)

| Audit | When | Why always |
|---|---|---|
| Architecture | Onboarding, major change, pre-Mode A | Drift/coupling/tech-debt baseline |
| Code Quality | Legacy, recurring bugs, pre-milestone | Maintainability + defect pattern |
| Security | Security-sensitive project, post-incident, periodic | AI-runtime + code-level threats |
| Performance | Pre-launch, slow pages, scaling | Critical-path eCommerce risk |
| Deployment Readiness | Pre-release | Go/no-go gate |
| Estimation | Monthly, sprint planning | Estimate accuracy |
| AI Output | Post-incident, periodic governance | Hard-gate + blind-trust compliance |
| Documentation | Monthly health check | Doc debt / staleness |

## Conditional audits (platform/project-type)

| Audit | Include when | Source section |
|---|---|---|
| Magento | `platform = magento` | audit-workflows §5 Magento |
| Shopify | `platform = shopify` | audit-workflows §6 Shopify |
| Hyva | `stack_variant = magento-hyva` (or Hyva migration) | audit-workflows §Hyva (v4 new) |
| API | headless / `stack_variant` contains "headless" / `project_type = integration` / middleware | audit-workflows §API (v4 new) |

---

## Selection matrix by project type (examples)

| Project type | Audits included |
|---|---|
| Magento (Luma/Hyva/Headless) | Base + Magento (+ Hyva if Hyva) (+ API if Headless) |
| Shopify (Liquid/Headless) | Base + Shopify (+ API if Headless) |
| Next.js / Headless | Base + API |
| Laravel (API backend) | Base + API |
| Middleware / Integration | Base + API |
| Maintenance (unknown platform) | Base only (add platform audit once detected) |

> Blueprint S15 may override via `audits_to_include` (`auto` | explicit list | `auto + X`). An explicitly named audit not in the 12 is skipped with a non-blocking warning.

## Generator output

For each selected audit, generate `.ai/audits/{type}/README.md` (Vietnamese) — the audit definition sourced from `workflow-guides/audit-workflows.md`, formatted per the standard output. Project-owned evidence goes under `.ai/evidence/`.

## Cross-References

- Audit content (all 12): `workflow-guides/audit-workflows.md`
- Project Auditor agent: `shared-core/agents/project-auditor.md`
- Evidence policy: `shared-core/evidence/evidence-policy.md`
- Selection logic siblings: `generator-rules/agent-selection-rules.md`, `generator-rules/skill-selection-rules.md`
