# Agents Index

> **Language:** English (selection meta). Agent definitions (`{name}.md`, Vietnamese) are copied as-is to `.ai/agents/`. Selection logic also encoded in [`generator-rules/agent-selection-rules.md`](../../generator-rules/agent-selection-rules.md).

Agents are **tool-agnostic markdown doc definitions** (not Claude YAML subagents) — rich fields readable by Claude Code, Codex, and Copilot as context. Each agent: Purpose / When to use / Required inputs / Expected outputs / Boundaries / Handoff rules / Required skills / Required memory files / Review checklist.

> Reuse: the 6 delivery-role agents (ba, sa, tl, developer, qc, devops) reframe the existing `role-guides/` (team-internal). The reviewer/specialist agents (security-reviewer, performance-reviewer, magento-reviewer, shopify-reviewer, hyva-migration, api-integration, legacy-code-auditor) consolidate the "reviewer hats" that were previously skills-only. `project-auditor` reframes `role-guides/auditor.md`.

---

## Base agents (every project)

| Agent | File | Purpose (one line) |
|---|---|---|
| BA | `ba.md` | Requirements, discovery, spec, ticket, client scope |
| Solution Architect | `sa.md` | Architecture decisions, integration contracts, design review |
| Technical Lead | `tl.md` | Plan approval, code review, gates, escalation |
| Developer | `developer.md` | Implement per plan, review-code, PR, context update |
| QC | `qc.md` | Test cases, acceptance verification, regression |
| DevOps | `devops.md` | CI/CD, environments, deploy, rollback |

## Conditional agents (platform / project-type)

| Agent | File | Include when |
|---|---|---|
| Security Reviewer | `security-reviewer.md` | project touches auth/PII/payment/secret (most projects) |
| Performance Reviewer | `performance-reviewer.md` | pre-launch / scaling / slow-path work |
| Magento Reviewer | `magento-reviewer.md` | `platform = magento` |
| Shopify Reviewer | `shopify-reviewer.md` | `platform = shopify` |
| Hyva Migration | `hyva-migration.md` | `stack_variant = magento-hyva` OR Luma→Hyva migration project |
| API Integration | `api-integration.md` | headless / `stack_variant` contains "headless" / `project_type = integration` / middleware |
| Legacy Code Auditor | `legacy-code-auditor.md` | `project_type` in (migration, maintenance) OR legacy codebase |
| Project Auditor | `project-auditor.md` | every project (runs audits cross-cutting) |

## Selection summary

- **Always (6):** ba, sa, tl, developer, qc, devops, project-auditor (7 with auditor).
- **By platform:** + magento-reviewer (magento) / shopify-reviewer (shopify).
- **By variant:** + hyva-migration (hyva), + api-integration (headless).
- **By type:** + legacy-code-auditor (migration/maintenance/legacy).
- **By risk flags (S12):** + security-reviewer (auth/PII/payment), + performance-reviewer (performance risk).

> Blueprint S15 `agents_to_include` override: `auto` (default) | `auto + name` | explicit list | explicit + auto. An explicitly named agent not defined here is skipped with a non-blocking warning.

## Cross-References

- Selection rules: `generator-rules/agent-selection-rules.md`
- Team-internal role guides (richer, Vietnamese): `role-guides/`
- Skills index: `skills-source/INDEX.md`
- Reviewer-hats mapping (original): `role-guides/README.md`
