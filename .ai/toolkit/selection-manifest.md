# Selection Manifest — Secomm Launchpad (Magento 2.4.8 Hyvä)

**Example mode:** `false` (real client project — no `.EXAMPLE_MODE` sentinel).

> What the v4 generator selected for this project and why (mapped to blueprint fields). Verify against `generator-rules/agent-selection-rules.md` + `PROJECT_BLUEPRINT_TO_TOOLKIT_MAPPING.md`.

**Detected stack:** `magento-hyva` (platform=magento + stack_variant=magento-hyva) — confidence high. **Mode:** A (new-build, >16h). **Output language:** vi.

---

## Agents selected (11)

| Agent | Trigger (blueprint field) | Source |
|---|---|---|
| ba, sa, tl, developer, qc, devops, project-auditor | Always (base 7) | `shared-core/agents/` |
| **magento-reviewer** | S2 `platform = magento` | `shared-core/agents/magento-reviewer.md` |
| **hyva-migration** | S2 `stack_variant = magento-hyva` | `shared-core/agents/hyva-migration.md` |
| **security-reviewer** | S12 `high_risk_areas` (VNPAY payment/IPN) + S9 `escalation_areas` (payment) | `shared-core/agents/security-reviewer.md` |
| **performance-reviewer** | S12 infra/perf risk (unconfigured search/cache, checkout hot path) | `shared-core/agents/performance-reviewer.md` |

> Not selected: `shopify-reviewer` (not Shopify), `api-integration` (not headless/external API integration), `legacy-code-auditor` (new-build, not migration/maintenance).

## Skills selected (`.claude/skills/`)

- **Base 9**: task, spec, review-code, testcase, deploy, update-memory, compact-context, continue, status
- **Magento conditional**: `magento-module-analysis` (platform=magento), `magento-checkout-impact` (S7A checkout_customization = Mageplaza OSC), `security-review` (S12 payment/IPN)
- **Not selected**: `magento-upgrade-review` (new-build, no version delta), `shopify-*`, `headless-api-contract-review`, `incident-analysis` (not maintenance/hotfix), `sync-toolkit-updates`
- **Dev skills** (`.claude/skills/dev/`): Magento (create-module, create-plugin, create-observer, create-db-schema, create-cron-job, create-api-endpoint, create-admin-grid, create-graphql-resolver) + Hyvä (hyva-alpine-component, hyva-tailwind-section)

## Instincts / Rules / Hooks / MCP / Commands / Evidence (deterministic — all projects)

- `.ai/instincts/INSTINCTS.md` ← `shared-core/instincts/instincts.md`
- `.ai/rules/` (11) ← `shared-core/rules/`
- `.ai/hooks/` (12, split from HOOKS.md) — emphasized for this project: `before-security-sensitive-change` (VNPAY/payment), `before-dependency-install` (composer/npm + Hyva Packagist token), `before-shell-command`, `before-commit` (no secret/token leak)
- `.ai/mcp/` (4) ← `shared-core/mcp/` — default read-only; no DB MCP enabled
- `.ai/commands/` ← `shared-core/commands/`
- `.ai/evidence/evidence-policy.md` ← `shared-core/evidence/evidence-policy.md`

## Audits selected

- **Base**: architecture, code-quality, security, performance, deployment, estimation, ai-output, documentation
- **Magento** (platform=magento): `magento` audit
- **Hyva** (stack_variant=magento-hyva): `hyva` audit
- Not selected: shopify, api audits

## Engineering Standards & Capabilities (v4)

- **Detected capabilities**: Magento Backend Development, Hyva Migration/Frontend, Payment Integration Security, Checkout Customization, Address/Localization, Security Review, Performance Optimization, Architecture Design, Documentation.
- **Engineering Standards** (`.ai/project-context/engineering-standards/`): ENGINEERING_PRINCIPLES + base + `magento.md` + `hyva.md` + `php.md` (+ mysql, graphql, rest-api, cicd, docker technologies).
- **Research Profile**: `magento + hyva` (generated `.ai/research/`).

## Functions selected

- **Base** (always): analyze-ticket, create-discovery-questions, create-solution-design, create-feature-spec, split-feature-into-tasks, estimate-feature, research-implementation, implement-task, review-code, generate-test-cases, prepare-qc-handoff, prepare-deployment-checklist, audit-architecture, audit-security, audit-performance, summarize-progress, compact-context, resume-work, record-decision, record-risk, record-lesson, update-project-ai-tool, + others (see `function-index.md`).
- **Magento (6)**: audit-hyva-template, review-magento-layout-xml, inspect-viewmodel, check-cache-impact, check-checkout-impact, validate-theme-build.
- **Security + Performance** (selected — payment/checkout/infra risk present): `security/`, `performance/` function sets.
- Not selected: shopify, middleware functions (not the stack).
- See `function-index.md`; selection per `generator-rules/function-selection-rules.md`.

## Memory files (all generated)

`project-context/memory/`: CURRENT_STATE, NEXT_TASK, DECISIONS, LESSONS_LEARNED, CONTINUOUS_LEARNING, RESEARCH_NOTES, **SECURITY_BASELINE** (project security level = HIGH — VNPAY/Mollie payment, IPN, PII; filled from S9/S12), KNOWN_RISKS (pointer → `06`).

## Project-specific emphasis (from blueprint)

- **VNPAY payment** (custom gateway, IPN/signature, default inactive) → security-reviewer + security functions + Tier-2 escalation flag in AGENTS.md §11/§12.
- **Mageplaza OSC checkout** → magento-checkout-impact + Tier-2 escalation.
- **Hyvä rendering** (Tailwind v4 CSS-first + Alpine + Magewire) → hyva-migration agent + hyva dev skills + Tailwind v4 correction (no tailwind.config.js).
- **VN address dropdown** (Secomm_AddressDropdown + data) → review-magento-layout-xml + inspect-viewmodel.

## Capability Propagation (traceability)

> Schema: `PROJECT_CAPABILITY_PROPAGATION_AUDIT.md` §10; validated by `GENERATOR_VALIDATION_CHECKLIST.md` §25.

**toolkit_version_at_generation:** `4.0` · **generated:** `2026-07-14`

### generated (received at runtime)

| id | source | source_version | destination | mode | validation_status |
|---|---|---|---|---|---|
| outcome-execution | `execution-policy/*` (6 policies) | 4.0 | `.ai/AGENTS.md` §8.5 | transformed (distilled) | pass — §8.5 present, self-contained |
| execution-efficiency | `execution-policy/AGENT_EXECUTION_EFFICIENCY_POLICY.md` | 4.0 | `.ai/AGENTS.md` §8.6 | transformed (distilled) | pass |
| safety-governance | `core/*` (governance) | 4.0 | `.ai/AGENTS.md` §7.x, §10–§12 | transformed (distilled) | pass |
| coding-rules | `coding-rules/shared` + `magento` + Hyva/Tailwind v4 | 4.0 | `.ai/project-context/CODING_RULES.md` + AGENTS §7.2 | composed (merge) | pass |
| instincts | `shared-core/instincts/instincts.md` | 4.0 | `.ai/instincts/INSTINCTS.md` | copied | pass |
| rules | `shared-core/rules/` (11) | 4.0 | `.ai/rules/` | copied | pass (provenance refs → toolkit) |
| hooks | `shared-core/hooks/HOOKS.md` (split) | 4.0 | `.ai/hooks/` (12) | transformed (split) | pass |
| agents | `shared-core/agents/{selected}` | 4.0 | `.ai/agents/` (11) | copied + reframed | pass |
| skills | `skills-source/{base 9 + magento + security}` + dev/magento | 4.0 | `.claude/skills/` + `.claude/skills/dev/` | copied | pass |
| functions | `shared-core/functions/{base + magento + hyva + security + performance}` | 4.0 | `.ai/functions/` | copied | pass |
| engineering-standards | `shared-core/engineering-standards/{base + magento + hyva + php + tech}` | 4.0 | `.ai/project-context/engineering-standards/` | selected | pass |
| navigator-runtime | `shared-core/navigator/templates` | 4.0 | `.ai/runtime/` (yaml + renders) | transformed (rendered) | pass |
| research-engine | `shared-core/research/` | 4.0 | `.ai/research/` | transformed (rendered) | pass |
| workflow-engine | `shared-core/workflows/` | 4.0 | `.ai/workflow/` | transformed (rendered) | pass |
| intent-engine | `shared-core/intents/` | 4.0 | `.ai/intents/` | transformed (rendered) | pass |
| initialization | `shared-core/initialization/` | 4.0 | `.ai/initialization/` | transformed (rendered) | pass |
| enablement | `shared-core/enablement/` | 4.0 | `.ai/WELCOME.md` + guides/reference/learning | transformed (rendered) | pass |
| copilot | `copilot/copilot-instructions.template.md` | 4.0 | `.github/copilot-instructions.md` | composed | pass |
| codex | `codex/AGENTS.template.md` | 4.0 | `codex/AGENTS.md` | composed | pass |

### excluded (generator-only or not-enabled)

| id | reason |
|---|---|
| project-selection-safety (Step 0 preflight) | `generator_only` |
| `GENERATOR_VALIDATION_CHECKLIST` / `output-file-rules.md` / `mapping-rules.md` | `generator_only` |
| engine specs (`DELIVERY_WORKFLOW_ENGINE`, `NAVIGATOR_ENGINE`, `INTENT_ENGINE`, `TRANSITION_POLICY`, …) | `generator_only` (behaviour baked into functions/agents/AGENTS.md/runtime renders) |
| `core/` + `execution-policy/` folders | `generator_only` (distilled into AGENTS.md §7.x / §8.5 / §8.6) |
| `shopify-reviewer` / shopify functions / shopify audit / shopify dev skills | `unrelated_stack` |
| `magento-upgrade-review` skill | `not_enabled` (new-build, no version delta) |
| `legacy-code-auditor` / `api-integration` agents | `not_enabled` |
| `headless-api-contract-review` / `incident-analysis` skills | `not_enabled` |
| shopify/laravel/middleware functions | `unrelated_stack` |

### pending

| id | reason |
|---|---|
| production infrastructure config (Redis/Varnish/OpenSearch/CI) | project decision pending (blueprint DEC-2) |
| multi-store scope resolution | project decision pending (blueprint DEC-1) |
| VNPAY enablement + security hardening | project decision pending (blueprint DEC-6) |

## Validation check

- [ ] All required files present (GENERATOR_VALIDATION_CHECKLIST) — see `.ai/toolkit/validation-report.md`
- [ ] No `shopify-*` / `legacy-code-auditor` / `api-integration` (correct exclusions)
- [ ] KNOWN_RISKS is a pointer (not a data store)
- [ ] Language: agents/rules/hooks/instincts/README/memory/navigator/guides = VI; AGENTS.md/project-context/skills/mcp/commands = EN
- [ ] Tailwind v4 (no tailwind.config.js) reflected in AGENTS §2/§7.2 + CODING_RULES
