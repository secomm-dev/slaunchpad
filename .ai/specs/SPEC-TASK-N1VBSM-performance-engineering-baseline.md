# Mini-Spec: TASK-N1VBSM (LC-23 Performance Engineering Baseline)

- **ID**: `TASK-N1VBSM` (External Ref: `LC-23`)
- **Parent**: `FEAT-LC05`
- **Priority**: P1 (High)
- **Workflow Mode**: C

---

## 1. Summary
Establish the mandatory non-functional requirements (NFR) baseline for the Launchpad platform (reusable components/modules) to gate performance quality before any component is approved into the Launchpad reusable package.

## 2. Acceptance Criteria
- **AC-001 (NFR Checklist)**: Component/extension review checklist covering: asset discipline (Alpine.js, Tailwind CSS v4, asset pipeline), cacheability/FPC (lifetime, cache key identity dimensions, invalidation tags, private content separation), image/lazy-load (CLS protection, width/height/aspect-ratio, above-the-fold exemption), and third-party integration audit.
- **AC-002 (Performance Budget)**: Concrete budgets for: PLP/PDP (Lighthouse metrics — LCP, TBT, CLS, payloads), Product Card (isolated metrics — JS, CSS, DOM node count), key interactions (Add to Cart, Filter, Autocomplete Search) measured via Playwright E2E timing (p50/p95, 30 runs), and OpenSearch query/index product-level baselines.
- **AC-003 (Search Matrix)**: Full stack combination compatibility matrix (Magento + OpenSearch + ElasticSuite + Hyvä Compat + Hyvä UI version), tiered status (Supported, Tested, Not Tested, Deprecated), deprecated rows retained for audit history, composer constraints and official tracker links.
- **AC-004 (Benchmark Procedure)**: Repeatable benchmark procedure (Lighthouse CI + Playwright E2E), standardised environment (Launchpad Reference Storefront LC-02, Hyvä Default Theme fallback), diagnostic tooling (Blackfire/PHP profiler), regression definition and Quality Gate pass/fail/warning matrix.

## 3. Out of Scope
- Infrastructure provisioning/tuning (Redis, Valkey, Varnish, CDN, server sizing).
- Client-specific SLAs or production traffic tuning.
- Third-party API/service optimisation outside component source code.
