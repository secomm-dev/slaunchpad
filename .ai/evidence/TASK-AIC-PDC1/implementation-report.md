# TASK-AIC-PDC1 — product-detail response cache + ETag (evidence)

- Spec: `.ai/specs/SPEC-TASK-AIC-PDC1-ai-commerce-product-detail-cache.md` (MINI, Mode C)
- Plan: `.ai/plans/TASK-AIC-PDC1-implementation-plan.md`
- Branch: `task/ai-commerce-product-detail-cache` (base `bd6e34e6`)
- Audit ref: `.ai/audits/AI-COMMERCE-PRODUCTION-HARDENING-20260825.md` P1.1
- Date: 2026-08-25

## 1. Files changed

| File | Change |
|---|---|
| `Controller/Products/View.php` | ResponseCache lookup (route `product`, params `['sku'=>trim]`) before fetch; warm hit skips ProductFetcher; miss → fetch + save + lifetime-aware respond. Enabled-check before cache IO; errors thrown before any save. |
| `Observer/StockInvalidation.php` (new) | `clean_cache_by_tags` observer: `IdentityInterface` with product cache-tag identity (`cat_p` / `cat_p_*`) → `InvalidateCache::cleanAll()` — covers MSI stock/salability (option B). |
| `etc/events.xml` | register `seocomm_aic_stock_change`. |
| `Model/InvalidateCache.php` | **Defect fix**: pass plain tags array to `CacheInterface::clean()` (Proxy contract) — Zend-style `clean($mode, $tags)` was a silent no-op at runtime. |
| `Test/Unit/Controller/Products/ViewTest.php` (new) | cold miss → fetch+save; warm hit → fetch/save never called; lifetime 0 forwarded; malformed SKU error uncached; disabled store short-circuits before cache IO. |
| `Test/Unit/Observer/StockInvalidationTest.php` (new) | `cat_p_42` / `cat_p` → cleanAll; non-product identities / non-identity object → no clean. |
| `Test/Unit/Model/InvalidateCacheTest.php` (new) | clean([tag]) contract pinned (mode-string regression). |
| `Test/Unit/Model/Cache/ResponseCacheTest.php` | product route key separates stores + SKUs; stable for identical input. |
| `README.md`, `CHANGELOG.md` (1.1.0) | cache contract + defect documentation. |

## 2. Root-cause finding (redis MONITOR)

`clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['secomm_aic'])` executed
`SINTER zc:ti:798_MATCHINGTAG zc:ti:798_MAGE` — the MODE STRING became a tag.
Runtime `Magento\Framework\App\CacheInterface` is `App\Cache\Proxy::clean($tags)`
(`App/Cache/Proxy.php:101`) → `App\Cache::clean($tags)` → MATCHING_ANY_TAG.
Consequence: ALL AiCommerce tag invalidation (product/category/config observers)
was a silent no-op on this stack — `ResponseCacheTest`/unit mocks could not see
it because they assert the call, not the Redis behavior. Fixed by passing
`clean([tag])`. **`Secomm_AiDiscoverability\Model\InvalidateCache` has the
identical latent bug (llms.txt cache) — reported as follow-up, out of scope here.**

## 3. MSI invalidation design (option B)

Stock changes via MSI indexers fire no `catalog_product_save_*`. Core
`module-inventory-cache` reacts to the same mutations by dispatching
`clean_cache_by_tags` with a `CacheContext` carrying
`Magento\Catalog\Model\Product::CACHE_TAG` identities (`cat_p_<id>`) — wired in
`module-inventory-cache/etc/di.xml` to both the sync source-item strategy and
the reservation salability queue. The new observer keys on exactly that signal,
giving the /ai cache the same coverage core gives FPC.

## 4. Runtime proof (vi_vn, SKU candlestick-brass-short)

- Cold: `200` + `cache-control: max-age=3600, public` + `etag: "b92de963…"` ✓
- Conditional: `304`, `size_download=0` ✓ (repeatable)
- Cache entry present in Redis: `zc:k:798_SECOMM_AIC_S3_PRODUCT_0CABB…` with
  tag sets `zc:ti:798_SECOMM_AIC` + `798_SECOMM_AIC_STORE_3` ✓
- Warm hit avoids ProductRepository/MSI/pricing: unit-proven
  (`testWarmHitSkipsPipelineAndSave` — fetch never called) + cache-entry
  presence (controller returns before fetch on hit). ETag itself does not
  avoid the Magento bootstrap (audit §5 stands).
- Store isolation: same SKU `store=default` vs `store=vi_vn` → distinct ETags
  (`ccc1638c…` vs `b92de963…`) → distinct cache keys/bodies; no leakage. (Both
  stores currently share VND pricing at merchant-data level — key separation is
  structural, not data-dependent.)
- Errors remain no-store: unknown SKU → 404 `no-store`; malformed SKU → 400
  `no-store`; neither cached.
- Invalidation runtime proof (no merchant data changed): warmed entry existed →
  dispatched `clean_cache_by_tags` with `CacheContext` registering
  `cat_p` + id 99999 via EventManager → **entry removed** (redis scan empty).
  /tmp proof scripts removed after use.

## 5. Validation

| Check | Result |
|---|---|
| AiCommerce suite | 63 tests / 104 assertions, OK (pre-existing allure warning) |
| AiDiscoverability regression | 62 tests / 153 assertions, OK |
| php -l (changed files) | clean |
| PHPCS Magento2 (changed files, warning-severity 6) | 0 errors / 0 warnings |
| setup:di:compile | OK (observer wiring changed) |
| project-ai-validate --check-specs | VALID |
| git diff --check | clean |
| app/etc/config.php | unchanged (working-tree env drift not committed) |

## 6. Follow-ups (NOT in this task)

- P1: `Secomm_AiDiscoverability\Model\InvalidateCache` same Proxy-clean bug —
  llms.txt cache invalidation currently a runtime no-op (bounded by 3600s TTL).
- Audit P1.2 edge rate limiting (Cloudflare) — infra.

## 7. Post-Implementation Review (MANDATORY)

- **magento-spec MCP used**: YES (`get_team_standards`, `get_review_gate`, `get_pattern_reference`)
- **Checklist Sections Reviewed**:
  - `infrastructure/cache-management.md` (Cache key dimensions, Proxy clean contract, tags array)
  - `inventory/inventory-msi.md` (MSI `clean_cache_by_tags` event, `CacheContext`, `cat_p` identities)
  - `ops/multi-store.md` (Store view isolation, per-store cache key scoping)
  - `ops/unit-testing.md` (Unit test quality, realistic mocks, exception propagation)
  - `core/routing-controllers.md` (Thin controller, `HttpGetActionInterface`, `Responder` delegation)
- **Cache Correctness**:
  - Store + SKU + Route cache key dimensions strictly isolated.
  - Warm hit skips `ProductFetcher` / DB / MSI pipeline completely.
  - Errors (400, 404, 500) remain `no-store`.
  - ETag matching (`If-None-Match`) produces 304 Not Modified.
  - Invalidation path covers product EAV, status, visibility, pricing, store config, and MSI stock updates.
- **MSI Invalidation**:
  - Observes `clean_cache_by_tags` carrying `cat_p` / `cat_p_<id>` tags from `Magento\InventoryCache`.
- **Store Isolation**:
  - `store` parameter strictly shapes the cache key namespace (`secomm_aic_s{storeId}_...`).
- **Magento Patterns**:
  - 0 ObjectManager calls, 0 core modifications, 0 raw SQL, explicit `CacheInterface::clean([tag])` plain array contract.
- **Review Severity Findings**:
  - P0 / BLOCKER: **0**
  - P1 / RECOMMENDATION: **0**
  - P2 / INFORMATIONAL: **0**
- **Fixes Made from Review**: None required.
- **Final Verdict**: **`PASS`**
- **Final HEAD SHA**: `c141972e`

