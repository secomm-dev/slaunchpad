# TASK-N1VBSM — Performance Engineering Baseline

**Legacy ID:** LC-23 *(re-identified per DEC-027; external_ref: LC-23)*

**Type:** Task (slice of FEAT-LC05 — Engineering Standards / Performance Baseline)
**Priority:** High (P1)
**Estimate:** ~8h
**Mode:** C (Documentation / Standards configuration, no production code change)
**Placement:** `.ai/project-context/engineering-standards/{PERFORMANCE_NFR_CHECKLIST.md,PERFORMANCE_BUDGET.md,SEARCH_STACK_COMPATIBILITY_MATRIX.md,PERFORMANCE_BENCHMARK_PROCEDURE.md}`
**Risk tier:** Tier 3 (documentation / engineering standards only — no production code / schema / payment / checkout change)
**Author:** AI draft · **Date:** 2026-08-21 · **Status:** Dev complete *(2026-08-21: 4 engineering standard files created under `engineering-standards/`, mini-spec + implementation plan created, post-review v2 fixes applied for Pass/Fail gate, OpenSearch baseline, ElasticSuite tested stack, Fast 3G labeling, conditional Device Category cache key, verified composer constraints, CWV wording clarification. Standardised all 4 artifacts and governance records to Technical English v3. Evidence: [TASK-N1VBSM evidence](../evidence/TASK-N1VBSM/evidence.md))*
**Specification:** MINI — embedded `## Mini Spec` below (ID: TASK-N1VBSM) · canonical record: [TASK-N1VBSM](../records/tasks/TASK-N1VBSM.md) (VALID) · Spec: [SPEC-TASK-N1VBSM](../specs/SPEC-TASK-N1VBSM-performance-engineering-baseline.md) · Plan: [TASK-N1VBSM plan](../plans/TASK-N1VBSM-implementation-plan.md)

## Description

Establish the mandatory NFR (Non-Functional Requirements) performance baseline for Secomm Launchpad, ensuring every new component/module meets internal engineering standards before approval into the Reusable Package. Baseline comprises 4 standalone technical documents under `.ai/project-context/engineering-standards/`:

1. **`PERFORMANCE_NFR_CHECKLIST.md`** — Yes/No/N-A checklist covering: Asset discipline (Alpine.js, Tailwind CSS v4, asset pipeline), Cacheability/FPC (lifetime, cache key identity dimensions, invalidation tags, private content separation), Image/lazy-load (CLS protection, aspect-ratio, above-the-fold exemption), and Third-party JS integration audit.
2. **`PERFORMANCE_BUDGET.md`** — Hard budget thresholds for: PLP/PDP page-level metrics (LCP, TBT, CLS, payloads), Product Card fixture component (JS/CSS payloads, DOM nodes, CLS), key user interactions (Add to Cart, Filter, Autocomplete Search) measured via Playwright E2E timing (p50/p95, 30 runs, Semantic Ready State), and OpenSearch query/index product-level baselines.
3. **`SEARCH_STACK_COMPATIBILITY_MATRIX.md`** — Full stack combination matrix (Magento + OpenSearch + ElasticSuite + Hyvä Compat + Hyvä UI version) with status tracking (Supported, Tested, Not Tested, Deprecated), composer constraints, and official tracker links.
4. **`PERFORMANCE_BENCHMARK_PROCEDURE.md`** — Repeatable benchmark procedure (Lighthouse CI + Playwright E2E), standardised environment (Fast 3G, CPU 4×, warm cache, 1,000 SKUs), regression detection rules, and Quality Gate decision matrix.

## Mini Spec

> Embedded Mini-Spec (ID: TASK-N1VBSM). Governs the acceptance criteria and engineering standard requirements for this slice.

### Goal

Provide concrete, review-ready NFR standards and performance budgets directly consumable by CI/PR review pipelines and QA/QC gates for the Launchpad Reusable Package.

### Expected Behavior

- Any PR/component submitted to Launchpad Reusable Package must pass `PERFORMANCE_NFR_CHECKLIST.md` with zero unresolved "No" items.
- Measured page-level and interaction metrics must conform to hard budgets in `PERFORMANCE_BUDGET.md`.
- Full search stack combinations must be tracked in `SEARCH_STACK_COMPATIBILITY_MATRIX.md`.
- Benchmark procedure is reproducible using Lighthouse CI and Playwright E2E adhering to `PERFORMANCE_BENCHMARK_PROCEDURE.md`.

### Constraints / Rules

- **Language convention**: All files under `engineering-standards/` must be 100% Technical English.
- **Naming convention**: SCREAMING_SNAKE_CASE filenames in `.ai/project-context/engineering-standards/`.
- **Quality Gate Matrix**:
  - **PASS**: Within Hard Budget AND Relative Regression < 5% vs Base Branch.
  - **WARNING**: Within Hard Budget BUT Relative Regression 5%–10% → Requires TL/SA review.
  - **FAIL (Block)**: Hard Budget violation OR Relative Regression > 10% OR any NFR checklist "No".
- **Throttle Profile**: Fast 3G (1.6 Mbps down / 750 Kbps up / 150 ms RTT) + CPU 4× slowdown.
- **Cache Key Identity**: Device category dimension is added conditionally ONLY when server-side HTML differs per device category.

### Out of Scope

- Infrastructure tuning (Redis/Valkey, Varnish, CDN, server sizing).
- Client-specific SLAs or production traffic load testing (k6 excluded from v1 baseline).
- Third-party external API optimization outside component source code.

### Acceptance Criteria

- [x] **AC-001 (NFR Checklist):** Checklist with 4 groups (Asset discipline, Cacheability/FPC, Image/CLS, Third-party JS) created at `PERFORMANCE_NFR_CHECKLIST.md`.
- [x] **AC-002 (Performance Budget):** Concrete budgets for PLP, PDP, Product Card fixture, Playwright p50/p95 interactions (Semantic Ready State), and OpenSearch query/index baseline created at `PERFORMANCE_BUDGET.md`.
- [x] **AC-003 (Search Matrix):** Full stack combination matrix (C-001 Tested via LC-05, C-002 Tested, C-003 Not Tested, C-004 Deprecated) with verified composer constraints created at `SEARCH_STACK_COMPATIBILITY_MATRIX.md`.
- [x] **AC-004 (Benchmark Procedure):** Repeatable benchmark procedure with Lighthouse CI + Playwright E2E, Fast 3G profile, and 3-tier Quality Gate matrix created at `PERFORMANCE_BENCHMARK_PROCEDURE.md`.

## Related

- Canonical Record: [TASK-N1VBSM](../records/tasks/TASK-N1VBSM.md)
- Spec: [SPEC-TASK-N1VBSM-performance-engineering-baseline.md](../specs/SPEC-TASK-N1VBSM-performance-engineering-baseline.md)
- Plan: [TASK-N1VBSM-implementation-plan.md](../plans/TASK-N1VBSM-implementation-plan.md)
- Evidence: [TASK-N1VBSM evidence](../evidence/TASK-N1VBSM/evidence.md)
- Artifacts:
  - `.ai/project-context/engineering-standards/PERFORMANCE_NFR_CHECKLIST.md`
  - `.ai/project-context/engineering-standards/PERFORMANCE_BUDGET.md`
  - `.ai/project-context/engineering-standards/SEARCH_STACK_COMPATIBILITY_MATRIX.md`
  - `.ai/project-context/engineering-standards/PERFORMANCE_BENCHMARK_PROCEDURE.md`
