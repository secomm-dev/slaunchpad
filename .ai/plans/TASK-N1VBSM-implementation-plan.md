# Implementation Plan: TASK-N1VBSM (LC-23 Performance Engineering Baseline)

- **Specification**: `.ai/specs/SPEC-TASK-N1VBSM-performance-engineering-baseline.md`
- **Task ID**: `TASK-N1VBSM` (External Ref: `LC-23`)
- **Mode**: C (Documentation / Standards)

---

## 1. Approach
Create 4 standalone engineering standard documents under `.ai/project-context/engineering-standards/` for CI/PR review integration and Quality Gate enforcement:
1. `PERFORMANCE_NFR_CHECKLIST.md`: Yes/No/N-A checklist for component/extension review.
2. `PERFORMANCE_BUDGET.md`: Hard budget thresholds for pages, components, interactions, and OpenSearch query/index metrics.
3. `SEARCH_STACK_COMPATIBILITY_MATRIX.md`: Full stack combination compatibility matrix with status tracking.
4. `PERFORMANCE_BENCHMARK_PROCEDURE.md`: Repeatable benchmark procedure with regression rules and pass/fail/warning gate.

## 2. Implementation Steps
- [x] **Step 1**: Create `PERFORMANCE_NFR_CHECKLIST.md` — 4 check groups: Asset discipline, Cacheability/FPC, Image/CLS, Third-party JS.
- [x] **Step 2**: Create `PERFORMANCE_BUDGET.md` — Page-level, Product card fixture, Playwright p50/p95 interactions (semantic ready state), and OpenSearch query/index baseline.
- [x] **Step 3**: Create `SEARCH_STACK_COMPATIBILITY_MATRIX.md` — Supported/Tested/Not Tested/Deprecated with tracker links and composer constraints.
- [x] **Step 4**: Create `PERFORMANCE_BENCHMARK_PROCEDURE.md` — measurement steps, standardised environment, regression detection, and Quality Gate decision matrix.
- [x] **Step 5**: Store all 4 documents under `.ai/project-context/engineering-standards/`, create evidence artifact at `.ai/evidence/TASK-N1VBSM/evidence.md`, and update working memory.

## 3. Risks & Mitigations
- *Risk*: Measurement noise from unstandardised dataset and network/device profiles.
  * *Mitigation*: Mandate Fast 3G throttling, CPU 4× slowdown, and a pinned 1,000 SKU warm-cache dataset.
- *Risk*: Combinatorial explosion in the Search Stack Compatibility Matrix.
  * *Mitigation*: Restrict the matrix to combinations Launchpad actually supports and intends to use.
