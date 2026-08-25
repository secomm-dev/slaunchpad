# PERFORMANCE_BUDGET

> Engineering standard — Performance budget thresholds for Launchpad components and key pages. English.
> All values are hard limits enforced at CI/PR review against the Launchpad Reusable Package.
>
> **Measurement environment**: Mobile emulation (Moto G4 / Pixel 5, viewport `360×640`), **Fast 3G** network throttling (1.6 Mbps down / 750 Kbps up / 150 ms RTT — standard Lighthouse/WebPageTest preset), CPU throttling 4×.

---

## 1. Page-Level Metrics (Lighthouse CI)

> Thresholds are set **stricter than** the Google Core Web Vitals "Good" thresholds (LCP ≤ 2.5 s, CLS ≤ 0.1) to maintain headroom for production traffic and third-party scripts. These baselines will be reviewed and tightened once the Launchpad Reference Storefront (LC-02) reaches stable status.

| Page Type | LCP | TBT | CLS | Total JS (gzip) | Total CSS (gzip) | DOM Nodes | Lighthouse Score |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Product Listing Page (PLP)** | ≤ 2.2 s | ≤ 150 ms | ≤ 0.05 | ≤ 120 KB | ≤ 35 KB | ≤ 1,200 | ≥ 90 / 100 |
| **Product Detail Page (PDP)** | ≤ 2.0 s | ≤ 120 ms | ≤ 0.02 | ≤ 110 KB | ≤ 35 KB | ≤ 1,000 | ≥ 92 / 100 |

---

## 2. Product Card Component Budget (Isolated Fixture Page)

> Lighthouse cannot isolate the cost of a single component on a real page. The Product Card is measured via a **standardised fixture URL** (a test page containing only the component, or a diff against a pinned reference PLP dataset).

| Metric | Budget Limit | Measurement Method | Notes |
| :--- | :---: | :--- | :--- |
| Component JS payload (isolated) | ≤ 8 KB | Network audit on fixture page | Includes Alpine.js component logic and inline state data. |
| Component CSS payload (isolated) | ≤ 3 KB | Tailwind build artifact analysis | Net CSS classes uniquely contributed by the product card. |
| DOM nodes per card | ≤ 25 nodes | DOM tree audit on fixture page | Ensures the card is lean — no redundant `<div>` nesting. |
| Image asset size (thumbnail) | ≤ 25 KB | Network audit (WebP/AVIF) | Product thumbnail after correct resizing. |
| CLS contribution | = 0.00 | Lighthouse CI on fixture page | Card must declare `aspect-ratio` or fixed image frame dimensions. |

---

## 3. Key Interaction Timing Budget (Playwright E2E)

> **Measurement rules** — Lighthouse does not support step-based interaction measurement. Use **Playwright browser E2E timing** with the following constraints:
> 1. **Start mark**: user action trigger (click / input event).
> 2. **End mark**: **semantic ready state** (defined per interaction via a DOM attribute or custom event). **Do NOT use `networkIdle`** as the primary completion condition — background analytics/polling requests cause unstable results; use `networkIdle` only as a secondary condition with a bounded timeout.
> 3. **Sample size**: minimum **30 runs / scenario**. Report **p50 (median)** and **p95 (95th percentile)**.
> 4. **Cache state**: warm cache (FPC warm, browser static assets cached).

| Interaction | Semantic Ready State Marker | p50 Target | p95 Limit | Secondary condition |
| :--- | :--- | :---: | :---: | :--- |
| **Add to Cart (PDP / PLP)** | `data-cart-status="updated"` present on mini-cart icon AND item count updated. | ≤ 350 ms | ≤ 650 ms | `networkIdle`, 1.5 s timeout. |
| **Layered Navigation Filter (PLP)** | `data-product-list-state="loaded"` set on the product list AND URL query string updated. | ≤ 400 ms | ≤ 750 ms | `networkIdle`, 1.5 s timeout. |
| **Search-as-you-type (Autocomplete)** | `.search-autocomplete-results` element contains results AND `aria-expanded="true"`. | ≤ 250 ms | ≤ 450 ms | `networkIdle`, 1.0 s timeout. |

---

## 4. OpenSearch / Query / Index Performance Baseline (Product Level)

> **Measurement tools**: Playwright E2E timing for client-observed search query latency; OpenSearch `_cat/indices` and Magento CLI for index-level metrics; Blackfire/PHP Profiler when tracing backend query origin.

| Metric | Budget Limit | Measurement Method | Notes |
| :--- | :---: | :--- | :--- |
| Search query latency (p50) | ≤ 50 ms | Playwright: time from keystroke to autocomplete render, minus network RTT. Or OpenSearch `_search` profile `took` field. | Measured on the standard 1,000 SKU warm-index dataset. |
| Search query latency (p95) | ≤ 80 ms | As above, 95th percentile over 30 runs. | p95 is the hard ceiling for complex queries (facet + sort + filter). |
| Full catalog reindex time (Core search) | ≤ 120 s | `bin/magento indexer:reindex catalogsearch_fulltext` — wall-clock time. | On the 1,000 SKU dataset, single-threaded. Take the median of 3 runs. |
| Full reindex time (ElasticSuite) | ≤ 180 s | `bin/magento indexer:reindex` for `elasticsuite_categories_fulltext` + `elasticsuite_thesaurus` — total wall-clock. | Includes category and thesaurus indices. |
| Index size per 1,000 SKUs | ≤ 150 MB | `curl localhost:9200/_cat/indices?v` → `store.size` for the `catalog_product` index. | Monitor for index bloat when adding custom attributes/facets. |
| Update-on-save partial reindex latency | ≤ 3 s | Change one product attribute via Admin → poll `bin/magento indexer:status` until `ready`. | Ensures partial reindex does not block the Admin workflow. |

---

## 5. Component Testability Requirement

For Playwright and other measurement tools to reliably capture **semantic ready state**:

- Every component serving a key interaction **must expose its working state** via a DOM attribute or custom event.
- Example contract:
  - When an AJAX/Magewire call begins: set `data-state="loading"`.
  - When DOM is fully updated and render is complete: set `data-state="ready"` or dispatch `window.dispatchEvent(new CustomEvent('launchpad:interaction-complete'))`.
- Components that cannot expose a semantic ready state are marked **Fail QC Review** for non-compliance with the testability standard.

---

## 6. Baseline Source & Review Schedule

> Numbers in this document represent a **proposed baseline, deliberately stricter than Google Core Web Vitals "Good" thresholds** (CWV Good: LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms) to maintain production headroom. The OpenSearch baseline is derived from benchmarks on the LC-05 verified stack (ElasticSuite 2.12.0 / OpenSearch 3.6.0 / 1,000 SKU dataset). All values will be formally reviewed once the **Launchpad Reference Storefront (LC-02)** reaches stable status.
