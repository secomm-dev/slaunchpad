# Engineering Standards — Framework Index

> **Language:** English (technical/code content). This module is the **single source of truth** for how Secomm projects design, implement, review, test, deploy, and maintain. Selection: [generator-rules/engineering-standards-selection-rules.md](../../generator-rules/engineering-standards-selection-rules.md). The generator is driven by the [Engineering Capability Matrix](./ENGINEERING_CAPABILITY_MATRIX.md).

Engineering Standards are **first-class**: they drive agent behavior, skill/function selection, rule enforcement, hook execution, audit criteria, and generation — not just documentation.

---

## Two layers (no duplication)

1. **`coding-rules/` (existing) → `CODING_RULES.md`** — the **enforcement-grade compact rules** (`[BLOCK]`/`[WARN]`), merged per stack into `project-context/CODING_RULES.md` (§7.2). These are the *hard checks*. **Stays as-is.**
2. **`shared-core/engineering-standards/` (this module) → `.ai/project-context/engineering-standards/`** — the **comprehensive standards library**: purpose, scope, mandatory rules, recommended practices, anti-patterns, validation checklists, examples, and cross-links. These are the *depth and reasoning*.

The library references `CODING_RULES.md` for enforcement items; `CODING_RULES.md`/`core/` point back for rationale. **One enforcement layer, one rationale layer — linked, not duplicated.**

---

## Every standard defines

`Purpose` · `Scope` · `Applicability` · `Mandatory Rules` · `Recommended Practices` · `Anti-patterns` · `Validation Checklist` · `Examples` · `Related` (Skills / Agents / Hooks / Rules / Functions / Audits / Memory / Project Types).

---

## Core

| File | Role |
|---|---|
| [ENGINEERING_PRINCIPLES.md](./ENGINEERING_PRINCIPLES.md) | 10 highest-priority principles → top instincts |
| [ENGINEERING_CAPABILITY_MATRIX.md](./ENGINEERING_CAPABILITY_MATRIX.md) | **Keystone** — capability → standards/agents/skills/functions/hooks/rules/audits/memory/evidence (drives generation) |
| [README.md](./README.md) | Explains the framework + enforcement flow (→ generated `engineering-standards/README.md`) |

## Standard docs (shared, generated when applicable)

`ARCHITECTURE_STANDARD` (patterns consolidated: Layered, DDD, Hexagonal, Event-Driven, Modular, Headless, API-First, Microservices, Monolith, Caching, Scalability, Integration) · `DEVELOPMENT_STANDARD` (DI, Error/Exception/Logging, Perf-coding, Secure-coding, Testing strategy, Refactoring, Tech Debt, Code Review) · `CODING_STANDARD` (principles + clean code + OOP + naming) · `COMMENT_STANDARD` · `SOLID_STANDARD` · `DESIGN_PATTERN_STANDARD` · `SECURITY_STANDARD` · `PERFORMANCE_STANDARD` · `TESTING_STANDARD` · `DOCUMENTATION_STANDARD` · `REVIEW_STANDARD` · `GIT_STANDARD` · `DEPLOYMENT_STANDARD` · `OPERATIONS_STANDARD` · `INCIDENT_STANDARD` · `CONTINUOUS_IMPROVEMENT_STANDARD` · `AI_ENGINEERING_STANDARD`.

## Technology standards (conditional) — `technologies/`

Selected per platform/stack; each may **extend or override** shared standards (more specific wins). `magento` (covers Adobe Commerce), `hyva`, `shopify`, `laravel`, `react` (covers Next.js), `php`, `typescript`, `graphql`, `rest-api`, `mysql`, `docker`, `cicd`.

---

## Selection (summary)

- **Always**: ENGINEERING_PRINCIPLES + README + the core shared standards (Architecture, Development, Coding, SOLID, Security, Performance, Testing, Review, Documentation, Deployment, Incident, Git, Continuous Improvement, AI Engineering).
- **By platform**: `magento` / `shopify` / `laravel` tech standards.
- **By variant/stack**: `hyva` (Magento Hyva); `react`/`typescript` (frontend); `php` (PHP backend); `graphql`/`rest-api` (API); `mysql` (DB); `docker`/`cicd` (deploy) — per detected capabilities.
- **Override**: blueprint S15 `engineering_standards_override` + existing `coding_rules_override`.

## Cross-References

- Selection rules: [generator-rules/engineering-standards-selection-rules.md](../../generator-rules/engineering-standards-selection-rules.md)
- Capability detection: [generator-rules/capability-detection-rules.md](../../generator-rules/capability-detection-rules.md)
- Capability matrix: [ENGINEERING_CAPABILITY_MATRIX.md](./ENGINEERING_CAPABILITY_MATRIX.md)
- Enforcement rules (compact): [`coding-rules/`](../../coding-rules/) → `CODING_RULES.md`
- Enforcement wiring: `shared-core/rules/engineering-standards-enforcement.md`
- Output: `generator-rules/output-file-rules.md` Rule 17 (engineering-standards folder)
