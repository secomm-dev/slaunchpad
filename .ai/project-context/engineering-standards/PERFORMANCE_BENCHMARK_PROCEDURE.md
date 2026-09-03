# PERFORMANCE_BENCHMARK_PROCEDURE

> Engineering standard — Repeatable baseline benchmark procedure. English.
> Defines how to measure, compare against PERFORMANCE_BUDGET, and prevent performance regressions across the Launchpad Reusable Package.

---

## 1. Tooling

### Mandatory Tools (v1 Mandatory)

| Tool | Scope | Purpose |
| :--- | :--- | :--- |
| **Lighthouse CI (LHCI)** | Page-Level Metrics (PLP, PDP) & Component Fixture Pages | Measures LCP, TBT, CLS, DOM nodes, payload size. |
| **Playwright Browser E2E** | Key Interactions (Add to Cart, Filter, Autocomplete Search) | Measures interaction latency (ms) at **p50** and **p95** percentiles over 20–30 runs/scenario using **Semantic Ready State**. |

### Optional Diagnostic Tools

| Tool | Trigger | Technical Notes |
| :--- | :--- | :--- |
| **Blackfire / PHP Profiler** | Suspected backend regression in queries, DI compilation, or Magewire lifecycle. | Traces call trees, SQL execution times, and memory peaks. |
| **Magento Profiler (Native)** | Used when Blackfire licence is unavailable. | Enabled via `SetEnv MAGE_PROFILER "html"` to profile block render times. |
| **N98-Magerun** | Verifying Magento cache/index/config state. | ⚠️ **N98-Magerun is NOT a profiler** — do not use it to profile query or render execution. |

> ⚠️ **Note**: **k6 is excluded from the v1 baseline**. k6 is a load-testing tool (throughput / concurrent users) belonging to production traffic tuning, which is out of scope for component-level quality gates.

---

## 2. Standardised Measurement Environment

- **Reference Storefront Target**: Run on the **Launchpad Reference Storefront based on LC-02 (Hyvä UI Foundation)** to accurately reflect the real Launchpad stack.
  - *Fallback*: If LC-02 is not yet stable, use **Hyvä Default Theme** as a temporary fallback, with a mandatory re-benchmark flag once LC-02 reaches stable.
- **Environment & Data Configuration**:
  - Magento Mode: `production` (DI compiled, static content deployed).
  - Dataset: Standardised pinned sample catalog (1,000 SKUs, 50 categories) to eliminate dataset noise across runs.
  - Cache State: **Warm Cache** (FPC warm, OPcache warm, static files cached).
- **Device & Network Profile**:
  - Device: Mobile emulation (Moto G4 / Pixel 5, viewport `360×640`).
  - Network Throttling: **Fast 3G** (1.6 Mbps down / 750 Kbps up / 150 ms RTT — standard Lighthouse/WebPageTest preset).
  - CPU Throttling: **4× slowdown**.

---

## 3. Step-by-Step Procedure

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ 1. Warm Cache   │ ──> │ 2. Lighthouse CI │ ──> │ 3. Playwright   │ ──> │ 4. Evaluate     │
│    & Dataset    │     │    Pages/Fixtures│     │    Interactions │     │    Gate Decision│
└─────────────────┘     └──────────────────┘     └─────────────────┘     └─────────────────┘
```

### Step 1: Prepare & Warm Cache
1. Deploy the candidate branch to the benchmark environment.
2. Reindex and warm target URLs:
   ```bash
   bin/magento indexer:reindex
   curl -s -o /dev/null https://benchmark.launchpad.local/fashion-women.html
   curl -s -o /dev/null https://benchmark.launchpad.local/sample-tshirt.html
   ```

### Step 2: Run Lighthouse CI (Pages & Component Fixtures)
1. Run LHCI with 5 iterations per target URL:
   ```bash
   lhci autorun --collect.numberOfRuns=5
   ```
2. Extract median values for LCP, TBT, CLS, JS/CSS payload, and DOM node count.

### Step 3: Run Playwright E2E Timing (Key Interactions)
1. Execute the timing script for the 3 key interactions (Add to Cart, PLP Filter, Autocomplete Search).
2. Repeat each scenario **30 times**.
3. Use `performance.now()` anchored to **Semantic Ready State**:
   ```javascript
   const start = performance.now();
   await page.click('[data-action="add-to-cart"]');
   // Wait for Semantic Ready State Marker
   await page.waitForSelector('[data-cart-status="updated"]', { timeout: 3000 });
   const duration = performance.now() - start;
   timings.push(duration);
   ```
4. Calculate and export **p50 (median)** and **p95 (95th percentile)**.

---

## 4. Comparison & Regression Rules

Compare all results against **PERFORMANCE_BUDGET.md**:

1. **Hard Budget Violation**: Any metric exceeding the budget limits:
   - PLP/PDP: LCP > 2.2 s, TBT > 150 ms, CLS > 0.05, JS payload > 120 KB.
   - Product Card: DOM nodes > 25, JS payload > 8 KB.
   - Interactions: p95 exceeding the interaction limit.
   - OpenSearch: Query latency > 80 ms, Full reindex > 120 s.
2. **Relative Performance Regression (vs Base Branch)**:
   - **Severe Regression (> 10%)**: LCP / interaction latency / OpenSearch query latency increases by > 10%, TBT increases by > 25 ms, or payload increases by > 5 KB. → **FAIL / BLOCK MERGE immediately, even if absolute values remain under the Hard Budget**.
   - **Moderate Regression (5%–10%)**: LCP or interaction latency increases by 5%–10%. → **WARNING / Escalate to Technical Lead or SA**.
   - **Minor Noise (< 5%)**: Increases below 5% are classified as acceptable measurement noise. → **PASS**.

---

## 5. Quality Gate Decision Matrix

Every PR or component submitted to the Launchpad Reusable Package must pass this gate:

| Gate Result | Trigger Conditions | CI / Review Action |
| :---: | :--- | :---: |
| **PASS** | All metrics within Hard Budget Limits **AND** Relative Regression < 5% vs Base Branch. | ✅ **Approve / Eligible for Merge** |
| **WARNING (Escalate)** | All metrics within Hard Budget Limits **BUT** Relative Regression is between 5% and 10%. | ⚠️ **Requires TL/SA Review**. Author must justify the regression before merge. |
| **FAIL (Blocked)** | Meets **any** of the following:<br>1. Violates any Hard Budget Limit.<br>2. Relative Regression > 10% vs Base Branch (even if within Hard Budget).<br>3. Any "No" in `PERFORMANCE_NFR_CHECKLIST.md`. | ❌ **Block Merge**. Author must refactor and optimize before re-running CI. |
