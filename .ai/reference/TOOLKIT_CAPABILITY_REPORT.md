# Toolkit Capability Report — Secomm Launchpad

<!-- AI/TL/auditor reference — NOT a human tutorial. English. Lists ONLY the actually-selected capabilities for this project (GENERATOR_VALIDATION_CHECKLIST §25). -->

> For AI / TL / auditors — not a human tutorial. This inventory lists ONLY capabilities actually selected/generated for this Magento 2.4.8 + Hyvä project. Capabilities not listed here were NOT selected and are NOT documented anywhere in the enablement kit.

## Tools supported

| Tool | Support level | Notes |
|---|---|---|
| Claude Code | **Primary** | Slash commands + natural language. Real skills in `.claude/skills/`. |
| Codex | Supported | NL; or paste command text as prompt snippet. |
| GitHub Copilot | Supported | NL; or paste command text as prompt snippet. |
| Cursor | **NOT supported** | Not documented. |

## Agents (roles) selected

**Base delivery roles (6):** `ba`, `sa`, `tl`, `developer`, `qc`, `devops`
**Conditional reviewers (4):** `magento-reviewer` (platform=magento), `hyva-migration` (variant=magento-hyva), `security-reviewer` (payment/PII), `performance-reviewer` (pre-launch/scaling)
**Cross-cutting (1):** `project-auditor`

| Agent file | Purpose |
|---|---|
| `.ai/agents/ba.md` | Requirements, discovery, spec, ticket, scope |
| `.ai/agents/sa.md` | Architecture, integration contract, design decision, ADR (Tier 2) |
| `.ai/agents/tl.md` | Quality gate, plan/code review, escalation Tier 1 |
| `.ai/agents/developer.md` | Implement per plan, review-code, PR, context update |
| `.ai/agents/qc.md` | Test cases, acceptance verification, regression |
| `.ai/agents/devops.md` | CI/CD, environments, deploy, rollback, secret |
| `.ai/agents/magento-reviewer.md` | Magento-specific review (plugins/preferences/layout/DI) |
| `.ai/agents/hyva-migration.md` | Hyvä template/viewmodel/Tailwind/Alpine review |
| `.ai/agents/security-reviewer.md` | Security review (payment/secret/permission) |
| `.ai/agents/performance-reviewer.md` | Performance review (cache/query/checkout path) |
| `.ai/agents/project-auditor.md` | Cross-cutting audits (architecture/security/performance/code-quality) |

## Skills selected

### Workflow skills (`.claude/skills/`)
| Skill | Selection trigger | Delivery area |
|---|---|---|
| `task` | base | Estimation, Documentation |
| `spec` | base | Documentation |
| `review-code` | base | QA/QC, Security |
| `testcase` | base | QA/QC |
| `deploy` | base | DevOps |
| `update-memory` | base | Documentation |
| `compact-context` | base | Documentation, Client comms |
| `continue` | base | Documentation |
| `status` | base | Client comms, Documentation |
| `magento-module-analysis` | platform=magento | Magento |
| `magento-checkout-impact` | magento + checkout/payment customization (Tier 2) | Magento |
| `security-review` | S12 payment/PII/secret | Security |

### Dev skills (`.claude/skills/dev/`) — Magento + Hyvä stack
`create-module`, `create-plugin`, `create-observer`, `create-db-schema`, `create-cron-job`, `create-api-endpoint`, `create-admin-grid`, `create-graphql-resolver`, `hyva-alpine-component`, `hyva-tailwind-section`

## Functions selected (`.ai/functions/`)

| Lifecycle area | Functions |
|---|---|
| **base** | analyze-ticket, create-discovery-questions, create-solution-design, create-feature-spec, split-feature-into-tasks, estimate-feature, research-implementation, implement-task, fix-bug, refactor-code, review-code, generate-test-cases, generate-unit-test, prepare-qc-handoff, prepare-pr, prepare-deployment-checklist, compact-context, resume-work, record-decision, record-risk, record-lesson, summarize-progress, audit-architecture, audit-security, audit-performance, audit-code-quality, update-project-ai-tool |
| **magento/** | audit-hyva-template, check-cache-impact, check-checkout-impact, inspect-viewmodel, review-magento-layout-xml, review-magento-module, review-plugin-preference-impact, validate-di-compile-impact, validate-theme-build |
| **hyva/** | review-hyva-template, validate-hyva-viewmodel, validate-tailwind-build, validate-alpine-behavior |
| **security/** | security-impact-review, secret-scan-check, permission-review |
| **performance/** | performance-impact-review, cache-impact-review, query-impact-review |

> Full index: `.ai/functions/function-index.md`. Initialization functions (migration set) NOT selected (project_type=new-build).

## Engineering standards selected (`.ai/project-context/engineering-standards/`)
ENGINEERING_PRINCIPLES, ARCHITECTURE, DEVELOPMENT, CODING, SOLID, COMMENT, DESIGN_PATTERN, TESTING, DOCUMENTATION, REVIEW, SECURITY, PERFORMANCE, DEPLOYMENT, OPERATIONS, INCIDENT, GIT, CONTINUOUS_IMPROVEMENT, BACKWARD_COMPATIBILITY + ENGINEERING_CAPABILITY_MATRIX. Technologies: `php`, `magento`, `hyva`, `mysql`, `graphql`, `rest-api`, `docker`, `cicd`.

## Commands (legacy shims → selected skills)
`/plan`, `/spec`, `/task`, `/review`, `/audit`, `/estimate`, `/compact-context`, `/resume-work`, `/update-memory`, `/record-decision`, `/record-risk`, `/record-lesson` (per `.ai/commands/commands-index.md`). Navigator commands: `/approve`, `/decisions`, `/evidence`, `/tbd`, `/help-nav`, `/explain`, `/next`.

## Entry points (≤4 primary, human-facing)
1. `.ai/WELCOME.md`
2. `.ai/runtime/PROJECT_NAVIGATOR.md`
3. `.ai/runtime/DECISION_QUEUE.md`
4. `.ai/guides/CHEATSHEET.md`

## Project-specific rules (coding_rules_override)
- Tailwind CSS v4 CSS-first (`@theme`/`@source` in `tailwind-source.css`); DO NOT create `tailwind.config.js`.
- Hyvä patterns: Alpine.js + Magewire 1.13; phtml-driven, no React/Vue.
- Vendor prefixes: `Secomm_` (project), `Vnpayment_` (payment); `Mageplaza_*` third-party — extend via plugin/preference only.
- PHP 8.2+ strict_types; Magento coding standard.
- Storefront strings → both `vi_VN.csv` and `en_US.csv`.
- VNPAY (payment/IPN/signature) change → SA review (Tier 2). Mageplaza OSC change → end-to-end checkout QC + payment test. Do NOT commit production env.php.

## Selection rationale
Capabilities selected by the toolkit generator from blueprint S15 (`skills_to_include: auto + magento-checkout-impact + magento-module-analysis + security-review`; `dev_skills_to_include: auto`) + platform/variant/type/risk detection. Nothing here is invented or roadmap.
