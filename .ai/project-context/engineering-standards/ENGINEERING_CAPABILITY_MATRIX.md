# Engineering Capability Matrix

> **The keystone.** The generation pipeline consults this matrix instead of hardcoded mappings: **blueprint → capability detection → matrix → artifacts**. Each capability row resolves the Engineering Standards, agents, skills, functions, hooks, rules, audits, memory, and evidence it needs. The agent/skill/function/audit columns **reference the existing selection rules** for the detailed mechanics — the matrix orchestrates; it does not restate every pick.

> **No duplication:** the matrix is the *capability → artifacts* index. Detailed per-domain selection (which specific agents/skills/functions) stays in `agent/skill/function-selection-rules.md` + `audits-index.md`, which the matrix "uses" column points to.

---

## How to read a row

| Capability | Engineering Standards | → Agents (via) | → Skills (via) | → Functions | → Hooks | → Rules | → Audits (via) | → Memory | → Evidence |

- **"via"** = consult the named selection rule for the detailed set; the matrix lists the capability-applicable subset/signal only.
- Capabilities compose: a Magento Hyvä B2B-pricing feature = *Magento Backend Development* + *Hyva Migration* + *Security Review* (payment) + *Performance Optimization* (cache).

---

## Capability → Artifacts

| Capability | Engineering Standards | → Agents (via) | → Skills (via) | → Functions | → Hooks | → Rules | → Audits (via) | → Memory | → Evidence |
|---|---|---|---|---|---|---|---|---|---|
| **Magento Backend Development** | Architecture, Development, Coding, SOLID, Security, Performance, Testing, Review, Git + `magento` tech | magento-reviewer, developer, sa, tl *(agent-selection-rules)* | magento-module-analysis, magento-checkout-impact, review-code *(skill-selection-rules)* | implement-task, review-code, audit-code-quality, refactor-code, generate-unit-test | before-task, before-commit, before-pr | planning-first, security-first, backward-compatibility, engineering-standards-enforcement | Magento, Code Quality, Security, Performance *(audits-index)* | RESEARCH_NOTES, DECISIONS, CURRENT_STATE | diff-summary, test-result, review-code |
| **Hyva Migration** | Architecture, Development, Coding, Performance + `hyva` tech | hyva-migration, magento-reviewer | magento-* + dev hyva-* | research-implementation, refactor-code, audit-code-quality | before-task, before-security-sensitive-change | backward-compatibility, project-conventions-first | Hyva, Magento, Code Quality | RESEARCH_NOTES (heavy), DECISIONS, CONTINUOUS_LEARNING | diff-summary, screenshot |
| **Shopify Checkout** | Architecture, Development, Security, Performance, Review + `shopify` tech | shopify-reviewer, security-reviewer | shopify-checkout-function-review, shopify-theme-review | review-code, audit-security, audit-performance | before-security-sensitive-change, before-deploy | security-first, backward-compatibility | Shopify, Security, Performance | SECURITY_BASELINE, DECISIONS | security-review, test-result |
| **API Integration** | Architecture, Development, Security, Performance, Testing + `graphql`/`rest-api` tech | api-integration, security-reviewer | headless-api-contract-review, security-review | review-api-contract, validate-idempotency, inspect-queue-retry, check-integration-logging | before-security-sensitive-change | backward-compatibility, security-first | API, Security, Code Quality | RESEARCH_NOTES, DECISIONS | contract-review, idempotency-test |
| **Performance Optimization** | Architecture, Performance, Development (perf-coding) + `mysql`/tech | performance-reviewer, sa | magento-checkout-impact, shopify-theme-review | audit-performance, refactor-code | before-deploy | production-readiness | Performance | CONTINUOUS_LEARNING, project-context/06 | perf-report, page-speed |
| **Security Review** | Security, Development (secure-coding), AI Engineering + tech | security-reviewer, project-auditor | security-review, review-code | audit-security, review-code | before-security-sensitive-change, before-dependency-install, before-shell-command | security-first | Security | SECURITY_BASELINE, LESSONS_LEARNED | security-review, secret-scan |
| **Deployment** | Deployment, Operations, Git, Incident + `docker`/`cicd` tech | devops, tl | deploy, incident-analysis | prepare-deployment-checklist, audit-performance (post-deploy) | before-deploy, after-deploy | production-readiness, security-first | Deployment Readiness, Performance | project-context/06, LESSONS_LEARNED | deploy, smoke-test |
| **Documentation** | Documentation, Continuous Improvement | ba, project-auditor | spec, update-memory, status | summarize-progress, prepare-pr | before-client-handoff | no-duplicate-knowledge, evidence-required | Documentation | CONTINUOUS_LEARNING | doc-diff |
| **Architecture Design** | Architecture, Development, Security, Performance, Review | sa, tl | headless-api-contract-review, magento-module-analysis | create-solution-design, research-implementation, audit-architecture | before-task | backward-compatibility, no-duplicate-knowledge | Architecture | DECISIONS, RESEARCH_NOTES | ADR, design-doc |
| **QC / Testing** | Testing, Development (testing strategy), Review | qc, developer | testcase, review-code | generate-test-cases, prepare-qc-handoff | before-pr (QC) | evidence-required | Code Quality, Testing | CONTINUOUS_LEARNING | test-result, qc-signoff |
| **Incident Response** | Incident, Operations, Security, Continuous Improvement | tl, devops, security-reviewer | incident-analysis, security-review | audit-security, summarize-progress | after-deploy | security-first, production-readiness | AI Output, Security | LESSONS_LEARNED, SECURITY_BASELINE | incident-report, post-mortem |
| **Legacy / Migration** | Architecture, Development, Refactoring, Continuous Improvement + tech | legacy-code-auditor, sa | magento-module-analysis, magento-upgrade-review, headless-api-contract-review | research-implementation, refactor-code, audit-code-quality | before-security-sensitive-change | backward-compatibility, project-conventions-first | Code Quality, Architecture, Documentation | RESEARCH_NOTES, project-context/06 | audit-report, debt-inventory |

---

## Capability detection signals (→ `capability-detection-rules.md`)

| Blueprint signal | Capability |
|---|---|
| S2 platform=magento + S7A custom_modules/checkout | Magento Backend Development |
| S2 stack_variant=magento-hyva OR migration | Hyva Migration |
| S2 platform=shopify + S7B checkout/functions | Shopify Checkout |
| S5 integrations (multi) OR project_type=integration OR headless | API Integration |
| S6 performance_requirements OR S12 perf risk | Performance Optimization |
| S9/S12 security/auth/PII/payment | Security Review |
| S11 deployment OR release | Deployment |
| S10 testing/UAT | QC / Testing |
| S12 incident OR project_type=hotfix | Incident Response |
| project_type in (migration, maintenance) OR legacy | Legacy / Migration |
| (always) | Architecture Design, Documentation |

---

## Generation flow (matrix-driven)

```
1. capability-detection-rules → detected capabilities []
2. For each capability → matrix row → collect: standards, agents(via), skills(via), functions, hooks, rules, audits(via), memory, evidence
3. engineering-standards-selection-rules → emit the union of standards (+ tech)
4. agent/skill/function/audit-selection-rules → resolve the "via" sets (detailed picks)
5. Emit deterministic artifacts (instincts, rules, hooks, mcp, evidence, commands)
6. Generate toolkit; validate (matrix consistency)
```

> The matrix is the **single orchestration point**. Adding a new capability = add a row; the pipeline picks it up. Adding a new artifact type = add a column.

## Cross-References

- Capability detection: [generator-rules/capability-detection-rules.md](../../generator-rules/capability-detection-rules.md)
- Standards selection: [generator-rules/engineering-standards-selection-rules.md](../../generator-rules/engineering-standards-selection-rules.md)
- Selection mechanics: `agent-selection-rules.md`, `skill-selection-rules.md`, `function-selection-rules.md`, `shared-core/audits/audits-index.md`
- Principles: `ENGINEERING_PRINCIPLES.md`
- Enforcement: `shared-core/rules/engineering-standards-enforcement.md`
