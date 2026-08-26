# Evidence: TASK-N1VBSM (LC-23 Performance Engineering Baseline)

- **Date**: 2026-08-21
- **Task ID**: `TASK-N1VBSM` (External Ref: `LC-23`)
- **Status**: Completed / Verified (v3 — Standardised to Technical English)

## Artifacts Generated & Refined

1. `.ai/project-context/engineering-standards/PERFORMANCE_NFR_CHECKLIST.md`:
   - Asset discipline, Cacheability/FPC (lifetime, key dimensions, tags, private content).
   - Cache key: Device category added only when server-side device rendering exists.
   - Image/CLS stability, Third-party JS audit.
   - **Language**: English (standardised with `engineering-standards/` conventions).
2. `.ai/project-context/engineering-standards/PERFORMANCE_BUDGET.md`:
   - Page-level budget (PLP, PDP) stricter than Google Core Web Vitals Good threshold.
   - Product card component isolated budget (Fixture page).
   - Key interactions Playwright E2E timing (p50/p95 over 30 runs, Semantic Ready State).
   - OpenSearch / Query / Index product-level baseline: Query latency (p50 ≤ 50 ms, p95 ≤ 80 ms), Reindex time (Core ≤ 120 s, ElasticSuite ≤ 180 s per 1,000 SKUs), Index size (≤ 150 MB per 1,000 SKUs), Partial reindex latency (≤ 3 s).
   - **Language**: English (standardised with `engineering-standards/` conventions).
3. `.ai/project-context/engineering-standards/SEARCH_STACK_COMPATIBILITY_MATRIX.md`:
   - Combination **C-001 (Tested)**: Magento 2.4.8-p5 + OpenSearch 3.6.0/2.12.x + ElasticSuite 2.12.0 + Hyvä Compat 1.2.8.0 + Hyvä Default Theme 1.5.2 (verified via LC-05).
   - Combination **C-002 (Tested)**: Magento 2.4.8-p5 + OpenSearch 2.12.x + Core Search Native (N/A ElasticSuite).
   - Combination **C-003 (Not Tested)**: ElasticSuite 2.11.x on Magento 2.4.8-p5.
   - Combination **C-004 (Deprecated)**: Magento 2.4.7-p4 / Hyvä 2.x combo.
   - Composer constraints verified against actual `composer.lock`.
   - **Language**: English (standardised with `engineering-standards/` conventions).
4. `.ai/project-context/engineering-standards/PERFORMANCE_BENCHMARK_PROCEDURE.md`:
   - Pass/Fail/Warning logic matrix:
     - **PASS**: All metrics within Hard Budget AND Relative Regression < 5%.
     - **WARNING**: Within Hard Budget BUT Relative Regression 5%–10% → Requires TL/SA review.
     - **FAIL (Block)**: Violates Hard Budget OR Relative Regression > 10% (even if within Hard Budget) OR any NFR Checklist failure.
   - Throttle profile explicitly labeled **Fast 3G** (1.6 Mbps down / 750 Kbps up / 150 ms RTT).
   - **Language**: English (standardised with `engineering-standards/` conventions).
