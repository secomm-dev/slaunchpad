# Skills Index

This index maps every skill in `skills-source/` to its **delivery area** and **selection type**, so it's clear which skill covers which need and where each belongs. It prevents duplicate skills and shows coverage at a glance.

Selection logic lives in [generator-rules/skill-selection-rules.md](../generator-rules/skill-selection-rules.md). Types:

- **Base** — copied into every project (always available)
- **Conditional** — copied when the blueprint/platform triggers it
- **Dev** — HOW-TO coding skills, copied to `.claude/skills/dev/` per stack
- **Toolkit-only** — never copied into a client project

> **Adobe Commerce = Magento.** Adobe Commerce is the enterprise edition of Magento. All `magento-*` skills apply to both Adobe Commerce and open-source Magento. There is no separate Adobe Commerce skill set.

---

## Workflow Skills (`.claude/skills/`)

| Skill | Selection | Primary delivery areas | One-line purpose |
|-------|-----------|------------------------|------------------|
| `task` | Base | Estimation, QA/QC, Documentation | Analyze a ticket: requirements, risks, affected areas, effort, escalation |
| `spec` | Base | Documentation | Draft a feature spec or mini-spec from a requirement/ticket |
| `review-code` | Base | QA/QC, Security | AI pre-review of code changes (scope, security, business rules) before TL review |
| `testcase` | Base | QA/QC | Generate QC test cases from spec/AC (happy/edge/negative) |
| `deploy` | Base | DevOps | Generate deployment checklist: pre-deploy, deploy, post-deploy, rollback |
| `update-memory` | Base | Documentation | Generate diffs to update `project-context/` after changes |
| `compact-context` | Base | Documentation, Client Communication | Compact the working session into memory files (context management) |
| `continue` | Base | Documentation | Reconstruct working state from memory files at session start |
| `status` | Base | Client Communication, Documentation | Produce a shareable project status from memory + context |
| `security-review` | Conditional | Security, Magento, Shopify | Review a change for AI-runtime + code-level security topics |
| `incident-analysis` | Conditional | DevOps, QA/QC | Production incident root-cause analysis (maintenance/hotfix) |
| `magento-module-analysis` | Conditional | Magento, Adobe Commerce | Magento module impact: plugins, preferences, observers, deps |
| `magento-checkout-impact` | Conditional | Magento, Adobe Commerce | Checkout/payment/shipping/order change impact (Tier 2) |
| `magento-upgrade-review` | Conditional | Magento, Adobe Commerce, Migration | Magento version upgrade compatibility review |
| `shopify-theme-review` | Conditional | Shopify | Shopify theme quality, performance, compatibility review |
| `shopify-checkout-function-review` | Conditional | Shopify | Shopify checkout extensions & Functions review (Tier 2 for checkout) |
| `headless-api-contract-review` | Conditional | React/Next.js, GraphQL/REST API, Laravel | API contract breaking-change review across frontend components |
| `sync-toolkit-updates` | Toolkit-only | — (toolkit management) | Propagate toolkit updates into generated projects (never copied to a project) |

### Conditional triggers (summary)

| Skill | Trigger |
|-------|---------|
| `security-review` | S12 has security/auth/PII/payment, or project flagged security-sensitive |
| `incident-analysis` | `project_type` = maintenance or hotfix |
| `magento-module-analysis` | `platform` = magento |
| `magento-checkout-impact` | magento + checkout/payment/shipping/order customization or high-risk |
| `magento-upgrade-review` | magento + upgrade project / version delta |
| `shopify-theme-review` | `platform` = shopify |
| `shopify-checkout-function-review` | shopify + checkout extensibility / Functions / payment-shipping rules |
| `headless-api-contract-review` | headless / nextjs / `stack_variant` contains "headless" |

---

## Dev Skills (`.claude/skills/dev/`)

HOW-TO coding skills, selected per detected stack. See [skill-selection-rules.md](../generator-rules/skill-selection-rules.md) → "Dev Skills Selection".

| Stack folder | Areas | Examples |
|--------------|-------|----------|
| `dev-skills/magento/` | Magento, Adobe Commerce, Hyvä | create-module, create-plugin, create-observer, create-db-schema, hyva-alpine-component, … |
| `dev-skills/shopify/` | Shopify | create-theme-section, create-metafield-structure, create-shopify-function, … |
| `dev-skills/headless/` | React/Next.js, GraphQL/REST API | create-page-with-data, create-api-route, create-product-listing, … |
| `dev-skills/laravel/` | Laravel, MySQL, GraphQL/REST API | create-api-endpoint, create-service-action, create-migration, create-feature-test, … |

---

## Coverage by Delivery Area

| Delivery area | Workflow skill(s) | Dev skill(s) |
|---------------|-------------------|--------------|
| Magento / Adobe Commerce | `magento-module-analysis`, `magento-checkout-impact`, `magento-upgrade-review`, `security-review` | `dev-skills/magento/` |
| Shopify | `shopify-theme-review`, `shopify-checkout-function-review`, `security-review` | `dev-skills/shopify/` |
| Hyvä | `magento-*` (Hyvä-aware) | `dev-skills/magento/` (hyva-*) |
| Laravel | `headless-api-contract-review`, `security-review` | `dev-skills/laravel/` |
| React / Next.js | `headless-api-contract-review` | `dev-skills/headless/` |
| GraphQL / REST API | `headless-api-contract-review` | laravel/headless dev skills |
| MySQL | (via laravel/magento dev skills + `CODING_RULES.md`) | `create-migration`, `create-db-schema` |
| DevOps | `deploy`, `incident-analysis` | — |
| Performance | `magento-checkout-impact`, `shopify-theme-review`, code-review/pre-review checks | — |
| Security | `security-review`, `review-code` (security items) | — |
| Estimation | `task`, `status` | — |
| Documentation | `spec`, `update-memory`, `compact-context`, `continue` | — |
| Client Communication | `status` (draft), templates (`uat-guide`, `release-note`) | — |
| QA / QC | `testcase`, `review-code`, `incident-analysis` | — |
| Migration | `magento-upgrade-review`, `headless-api-contract-review` | — |

> Gaps are intentional — areas like "Performance" and "MySQL" are covered by `CODING_RULES.md` + the pre-review/code-review checklists rather than dedicated skills, to avoid duplicating rule content. See [audit-workflows.md](../workflow-guides/audit-workflows.md) for performance/audit coverage.

---

## Skill Groups (v4 Generator)

Canonical 19 skill groups the generator organizes around, with cross-links to agents ([`shared-core/agents/`](../shared-core/agents/)) and hooks ([`shared-core/hooks/HOOKS.md`](../shared-core/hooks/HOOKS.md)). This is the v4 skill-group organization; per-skill detail stays in each `SKILL.md`.

| Group | Skills | Related agents | Related hooks |
|---|---|---|---|
| Magento | `magento-module-analysis`, `magento-checkout-impact`, `magento-upgrade-review` | magento-reviewer, hyva-migration | before-security-sensitive-change |
| Adobe Commerce | (= Magento — Adobe Commerce is Magento enterprise; same skills) | magento-reviewer | before-security-sensitive-change |
| Shopify | `shopify-theme-review`, `shopify-checkout-function-review` | shopify-reviewer | before-security-sensitive-change |
| Hyvä | `magento-*` (Hyvä-aware) + dev `hyva-alpine-component`, `hyva-tailwind-section` | hyva-migration | — |
| Laravel | `headless-api-contract-review` + dev `create-api-endpoint`, `create-service-action`, `create-migration`, `create-feature-test` | api-integration | before-security-sensitive-change |
| React / Next.js | `headless-api-contract-review` + dev `create-page-with-data`, `create-api-route` | api-integration | — |
| GraphQL | `headless-api-contract-review` | api-integration | — |
| REST API | `headless-api-contract-review` | api-integration | — |
| MySQL | dev `create-migration`, `create-db-schema` + `CODING_RULES.md` | sa (schema) | before-security-sensitive-change (DB) |
| DevOps | `deploy`, `incident-analysis` | devops | before-deploy, after-deploy |
| Performance | `magento-checkout-impact`, `shopify-theme-review` + pre-review perf checks | performance-reviewer | before-deploy |
| Security | `security-review`, `review-code` (security) | security-reviewer | before-dependency-install, before-shell-command, before-security-sensitive-change |
| Estimation | `task`, `status` | ba, project-auditor | — |
| Documentation | `spec`, `update-memory`, `compact-context`, `continue` | ba, developer | after-task |
| Client Communication | `status` (draft) + templates | ba | before-client-handoff |
| QA / QC | `testcase`, `review-code`, `incident-analysis` | qc | before-pr, after-deploy |
| Migration | `magento-upgrade-review`, `headless-api-contract-review` | hyva-migration, legacy-code-auditor | before-security-sensitive-change |
| Legacy Code Review | `magento-module-analysis`, `magento-upgrade-review`, `headless-api-contract-review`, `security-review` | legacy-code-auditor | before-security-sensitive-change |
| Integration / Middleware | `headless-api-contract-review`, `security-review` | api-integration | before-security-sensitive-change |

> Each `SKILL.md` carries Purpose / When / Prerequisites (= input files) / Input / Steps / Output Format / Quality Checklist / Escalation / Example. Cross-links to agents + hooks are centralized here (group-level) to avoid restating in every skill file (rule: no-duplicate-knowledge).

---

## Cross-References

- Selection logic: [skill-selection-rules.md](../generator-rules/skill-selection-rules.md)
- Copy mechanics: [output-file-rules.md](../generator-rules/output-file-rules.md) Rule 6 + Rule 14
- Skill-to-output mapping: [mapping-rules.md](../generator-rules/mapping-rules.md) → Dev Skills Mapping
- Toolkit propagation: [regeneration-rules.md](../generator-rules/regeneration-rules.md) → Toolkit Version Propagation
