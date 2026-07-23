# Engineering Standards

> Generated into `.ai/project-context/engineering-standards/README.md`. Explains the framework + how it's enforced across the lifecycle. (English — technical content.)

This project's engineering behavior is driven by **Engineering Standards** (not isolated coding guidelines). Standards are first-class: they drive agent behavior, skill/function selection, rule enforcement, hook execution, and audit criteria.

## What's here

- `ENGINEERING_PRINCIPLES.md` — the 10 highest-priority principles (top instincts).
- `ENGINEERING_CAPABILITY_MATRIX.md` (in the toolkit) — drives generation; the project's selected capabilities map to its standards.
- Category standards: `ARCHITECTURE_STANDARD`, `DEVELOPMENT_STANDARD`, `CODING_STANDARD`, `SOLID_STANDARD`, `COMMENT_STANDARD`, `DESIGN_PATTERN_STANDARD`, `SECURITY_STANDARD`, `PERFORMANCE_STANDARD`, `TESTING_STANDARD`, `DOCUMENTATION_STANDARD`, `REVIEW_STANDARD`, `DEPLOYMENT_STANDARD`, `INCIDENT_STANDARD`, (+ `GIT_STANDARD`, `OPERATIONS_STANDARD`, `CONTINUOUS_IMPROVEMENT_STANDARD`, `AI_ENGINEERING_STANDARD`).
- Technology standards: `{TECH}_STANDARD.md` for this project's stack (Magento/Shopify/Laravel/Hyva/React/PHP/TypeScript/GraphQL/REST-API/MySQL/Docker/CI-CD).
- `CODING_RULES.md` (sibling) — the enforcement-grade compact rules (`[BLOCK]`/`[WARN]`); these standards provide the depth/reasoning behind them.

## Enforcement flow (lifecycle)

```
Before Task   → load relevant Engineering Standards (function "Required Engineering Standards")
Implement     → Development + Technology + Security + Performance standards → self-validate → evidence
Review        → Review + Coding + Architecture standards → violations → fixes
Before PR     → validate Review standards (checklist)
Before Deploy → validate Deployment + critical Security standards
Audit         → standards as criteria → severity (S0–S3) → improvement recommendations
```

Rules may **block**: research-first blocks implementation; evidence-required blocks completion; security-first blocks deployment; architecture-review-required blocks major refactoring.

## Continuous improvement

Standards evolve during delivery via `update-project-ai-tool`: proposal → review → approve → update project standard → `CHANGELOG_AI_TOOL.md` → `DECISIONS.md` → `CONTINUOUS_LEARNING.md` → (if reusable) backport proposal to the toolkit. See `CONTINUOUS_IMPROVEMENT_STANDARD.md`.

## How AI uses these

1. Before any engineering action, load the standards the capability matrix names for that capability (function "Required Engineering Standards").
2. Apply ENGINEERING_PRINCIPLES first (highest priority).
3. Apply category + technology standards; technology may override shared (more specific wins).
4. Produce evidence; update memory.

## Cross-References

- Principles: `ENGINEERING_PRINCIPLES.md`
- Enforcement rule: `.ai/rules/engineering-standards-enforcement.md`
- Compact rules: `../CODING_RULES.md`
- Functions: `.ai/functions/function-index.md`
- Capability matrix (toolkit): `shared-core/engineering-standards/ENGINEERING_CAPABILITY_MATRIX.md`
