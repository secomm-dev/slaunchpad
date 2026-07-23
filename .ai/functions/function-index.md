# Functions Index

> **Language:** English (selection meta). Function recipes (`{name}.md`, Vietnamese) are copied to `.ai/functions/`. Selection logic: [generator-rules/function-selection-rules.md](../../generator-rules/function-selection-rules.md).

A **function** is a team-runnable recipe — the execution steps that **orchestrate** the project's skills, agents, rules, hooks, memory, and evidence. A function **depends on** (calls) existing skills/agents/rules; it does **not** duplicate them. E.g. the `analyze-ticket` function calls the `task` skill, names the files to read, the memory to update, and the evidence to save.

> Reuse principle (no-duplicate-knowledge): functions point to skills (`skills-source/`), agents (`shared-core/agents/`), rules (`shared-core/rules/`), hooks (`shared-core/hooks/`), and memory templates — they are the recipe layer, not a parallel knowledge base.

---

## Function template (every function defines)

See [`function-template.md`](./function-template.md) for the canonical enriched template (separated required agents/skills/rules/hooks/memory/evidence + output format + related audits/standards + 11-step runtime pattern + lifecycle area).

Core fields: `name` · `purpose` · `when to use` · `trigger` · `required inputs` · `required project files to read` · `required agents` · `required skills` · `required rules` · `required hooks` · `required memory files` · `required evidence` · `execution steps (11-step)` · `output format` · `failure handling` · `memory update rules` · `related audits` · `related standards` · `lifecycle area` · `when to improve`.

---

## Base functions (every project) — `shared-core/functions/`

Delivery lifecycle, in order:

| Function | Phase | Calls (skill/agent) |
|---|---|---|
| `analyze-ticket` | Requirement | task skill, ba/tl agents |
| `create-discovery-questions` | Requirement | ba agent, spec skill |
| `create-solution-design` | Design | sa agent, research-first rule |
| `create-feature-spec` | Spec | spec skill, ba/sa agents |
| `split-feature-into-tasks` | Planning | task skill, tl agent |
| `estimate-feature` | Planning | task skill, ba agent |
| `research-implementation` | Research | research-first rule, RESEARCH_NOTES |
| `implement-task` | Development | developer agent, planning-first rule, dev skills |
| `review-code` | Review | review-code (+security-review) skill, tl agent |
| `generate-test-cases` | Test | testcase skill, qc agent |
| `prepare-qc-handoff` | Test/QC | qc agent, testcase skill |
| `prepare-deployment-checklist` | Release | deploy skill, devops agent |
| `audit-architecture` | Audit | project-auditor, Architecture audit |
| `audit-security` | Audit | security-review skill, security-reviewer agent |
| `audit-performance` | Audit | performance-reviewer agent, Performance audit |
| `summarize-progress` | Status | status skill, ba agent |
| `audit-code-quality` | Audit | Code Quality audit (standards as criteria) |
| `refactor-code` | Refactor | review-code skill (preserve behavior) |
| `generate-unit-test` | Test | testcase skill, Testing standard |
| `prepare-pr` | Review | review-code skill, REVIEW standard |
| `compact-context` | Memory | compact-context skill |
| `resume-work` | Memory | continue skill |
| `record-decision` | Memory | DECISIONS.md append |
| `record-risk` | Memory | project-context/06 update |
| `record-lesson` | Memory | LESSONS_LEARNED / CONTINUOUS_LEARNING append |
| `update-project-ai-tool` | Self-update | the self-update subsystem (see below) |

## Initialization functions (migration projects)

| Function | Phase | Calls (skill/agent) |
|---|---|---|
| `audit-codebase` | Source audit | sa agent, research-implementation, audit-architecture |
| `assess-business-capabilities` | Business assessment | ba + sa agents, spec |
| `map-source-to-target-platform` | Target mapping | sa + tl agents, create-solution-design |
| `generate-migration-gap-analysis` | Gap analysis | sa + ba agents, audit-architecture |
| `assess-migration-complexity` | Complexity | sa + tl agents, audit-performance |
| `identify-discovery-focus` | Discovery prep | ba + sa agents, spec |
| `identify-estimation-drivers` | Estimation prep | ba + tl + pm agents, estimate-feature |
| `generate-current-system-assessment` | Assessment compile | sa + ba + tl agents, research-implementation |

## Stack-specific functions (conditional) — `shared-core/functions/{stack}/`

| Stack | Folder | Functions |
|---|---|---|
| Magento (Hyvä) | `magento/` | audit-hyva-template, review-magento-layout-xml, inspect-viewmodel, check-cache-impact, check-checkout-impact, validate-theme-build, review-magento-module, review-plugin-preference-impact, validate-di-compile-impact |
| **Hyvä** | `hyva/` | review-hyva-template, validate-hyva-viewmodel, validate-tailwind-build, validate-alpine-behavior |
| Shopify | `shopify/` | review-checkout-extensibility, review-shopify-functions, inspect-app-scope, validate-theme-change |
| Middleware / Integration / Laravel-API | `middleware/` | review-api-contract, inspect-queue-retry, validate-idempotency, check-integration-logging |
| **Security-sensitive** | `security/` | security-impact-review, secret-scan-check, permission-review |
| **Performance-sensitive** | `performance/` | performance-impact-review, cache-impact-review, query-impact-review |

> Headless (Next.js) projects reuse `middleware/review-api-contract` + the base set. Legacy/migration projects add Magento functions where applicable.

## Self-update subsystem

The functional layer self-improves during real delivery:

| Artifact | Source | Purpose |
|---|---|---|
| `.ai/functions/update-project-ai-tool.md` | `shared-core/functions/update-project-ai-tool.md` | The self-update function |
| `.ai/CHANGELOG_AI_TOOL.md` | `shared-core/memory/changelog-ai-tool-template.md` | Per-project AI-tool change log |
| `.ai/rules/ai-tool-self-update.md` | `shared-core/rules/ai-tool-self-update.md` | Self-update rule (constraints) |
| `.ai/hooks/before-ai-tool-update.md` | `shared-core/hooks/HOOKS.md` (section) | Pre-update hook |
| `.ai/hooks/after-ai-tool-update.md` | `shared-core/hooks/HOOKS.md` (section) | Post-update hook |

## Selection (summary)

- **Always**: 27 base functions (incl. `update-project-ai-tool`, `fix-bug`; +v4 engineering: `audit-code-quality`, `refactor-code`, `generate-unit-test`, `prepare-pr`).
- **Migration initialization**: 8 additional functions copied when `project_type = migration`.

## Lifecycle areas (logical grouping)

| Area | Functions |
|---|---|
| **workflow** | analyze-ticket, create-discovery-questions, create-solution-design, create-feature-spec, split-feature-into-tasks, estimate-feature, research-implementation, implement-task, **fix-bug**, refactor-code, generate-test-cases, generate-unit-test, prepare-qc-handoff, summarize-progress |
| **review** | review-code, prepare-pr, audit-architecture, audit-security, audit-performance, audit-code-quality |
| **memory** | compact-context, resume-work, record-decision, record-risk, record-lesson |
| **deployment** | prepare-deployment-checklist |
| **self-update** | update-project-ai-tool |
| **platform** | magento/ (9), shopify/ (4), middleware/ (4), hyva/ (4), security/ (3), performance/ (3) |
| **initialization** | audit-codebase, assess-business-capabilities, map-source-to-target-platform, generate-migration-gap-analysis, assess-migration-complexity, identify-discovery-focus, identify-estimation-drivers, generate-current-system-assessment |

> These are **logical tags** (not physical folders). Base functions are flat in `shared-core/functions/`; platform functions are in `{stack}/` subfolders. See [`function-template.md`](./function-template.md).

## Naming aliases

Some function names differ from earlier iterations; both work (old names are aliases, kept for backward compat):
- `review-magento-layout-xml` ≈ spec's `review-layout-xml`
- `inspect-queue-retry` ≈ spec's `validate-retry-strategy`
- `check-integration-logging` ≈ spec's `validate-integration-logging`
- `validate-theme-change` ≈ spec's `review-shopify-theme-change`
- **Magento**: + 6 magento functions.
- **Shopify**: + 4 shopify functions.
- **Middleware/Integration/Laravel-API**: + 4 middleware functions.
- **Headless**: base + `middleware/review-api-contract`.
- Blueprint S15 `functions_to_include` override (`auto` / explicit / `auto + X`).

## Cross-References

- Selection rules: `generator-rules/function-selection-rules.md`
- Output: `generator-rules/output-file-rules.md` Rule 17
- Skills (called by functions): `skills-source/INDEX.md`
- Agents: `shared-core/agents/INDEX.md`
- Rules/hooks/memory: `shared-core/rules/`, `shared-core/hooks/`, `shared-core/memory/`
