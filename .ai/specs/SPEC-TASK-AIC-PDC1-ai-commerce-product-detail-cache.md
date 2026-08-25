# SPEC-TASK-AIC-PDC1 — AI Commerce product-detail response cache + ETag (MINI, Mode C)

Date: 2026-08-25 · Module: `Secomm_AiCommerce` · Audit ref: `.ai/audits/AI-COMMERCE-PRODUCTION-HARDENING-20260825.md` P1.1

## 1. Problem

`GET /ai/products/{sku}` calls `Responder->json($data, 0)` → `Cache-Control: no-store`, no
ETag → every request repeats ProductRepository + MSI salability + pricing + url_rewrite work.
Under SKU-enumerating crawlers this is the easiest sustained-load surface (audit P1.1).

## 2. Goal

Bring `/ai/products/{sku}` onto the SAME bounded response-cache + ETag/304 contract as
`/ai/store`, `/ai/categories`, `/ai/catalog/search` — reusing the existing `ResponseCache`,
existing configured cache lifetime (`seocomm_ai_commerce/cache/lifetime`), and existing
`Responder` semantics. NO new admin field, NO second cache mechanism.

## 3. Design

### 3.1 Request flow (Controller/Products/View.php)

resolve store → per-store `isEnabled` check (unchanged, before cache) → `ResponseCache`
lookup with route `product` and normalized params `['sku' => trimmed sku]` → warm hit:
return cached DTO via `Responder->json($cached, $lifetime)` (no ProductRepository/MSI/pricing)
→ miss: existing `ProductFetcher::fetch()` pipeline, `ResponseCache::save`, same responder.

Cache key: existing `ResponseCache::key()` = `seomm_aic_s{storeId}_product_{sha1(sorted params)}`
— store and SKU are both part of the identity; `store` param is stripped by key(). Errors
(400 invalid SKU / 404 non-public / disabled) are thrown before save → never cached, stay
`no-store`.

### 3.2 Invalidation

- Product save/delete: existing `catalog_product_save_commit_after` /
  `catalog_product_delete_commit_after` observers (`cleanAll`) already fire on every product
  change (name, status, visibility, price, attributes) — unchanged.
- **MSI stock/salability**: stock state changes through `module-inventory-indexer`
  (source-item saves, reservation-driven salability updates) WITHOUT any
  `catalog_product_save_*` event. Core `module-inventory-cache` reacts by dispatching the
  Magento-native `clean_cache_by_tags` event with a `CacheContext` whose identities carry
  `Magento\Catalog\Model\Product::CACHE_TAG` (`cat_p_<id>`). New bounded observer:
  on `clean_cache_by_tags`, if the dispatched `IdentityInterface` object exposes any identity
  matching the product cache tag (`cat_p` prefix), run the same `InvalidateCache::cleanAll()`
  — identical correctness-first granularity as product saves. This also covers attribute-mass
  action cache cleans, harmlessly.
- Verdict vs task options: **B** (bounded Magento-native MSI invalidation observer).

### 3.3 Discovered runtime defect: tag invalidation was a silent no-op (FIXED)

Runtime proof (redis MONITOR) showed `clean(CLEANING_MODE_MATCHING_TAG, ['secomm_aic'])`
executed `SINTER zc:ti:798_MATCHINGTAG` — the mode string became a tag. Root cause: the
runtime `Magento\Framework\App\CacheInterface` is `App\Cache\Proxy`, whose deprecated
`clean($tags)` contract swallows the Zend-style `$mode` argument as the first tag, so ALL
AiCommerce tag invalidation (product/category/config observers) has been a silent no-op on
this stack. Fix: `InvalidateCache` now calls `clean([tag])` (Proxy contract,
MATCHING_ANY_TAG semantics; single tag ≡ matching). Unit-pinned in `InvalidateCacheTest`.
`Secomm_AiDiscoverability\Model\InvalidateCache` has the identical latent bug — llms.txt
scope, reported as follow-up, NOT changed here.

### 3.4 Response contract (unchanged DTO)

200 + `Cache-Control: public, max-age={configured lifetime}` + `ETag: sha1(body)` +
If-None-Match match → 304 empty body; HEAD empty body; errors `no-store`. No DTO/schema
change; no stock quantity (status only); explicit `?store=`; uniform 404; malformed SKU 400;
disabled → 404.

## 4. Out of scope

Edge rate limiting (infra), search cache cardinality, real-IP handling, any llms.txt change.

## 5. Acceptance

1. Cold request: 200, public Cache-Control, ETag; warm request serves from ResponseCache
   (pipeline provably not re-executed); conditional request → 304 empty body.
2. Same SKU in two store views never shares a cache entry (store in key) — runtime proof
   currency/price/locale differences preserved.
3. Errors remain no-store and uncached.
4. MSI stock change (via `clean_cache_by_tags` with product identities) invalidates cached
   detail; product save invalidates (existing).
5. Unit tests: miss→populate, warm hit no pipeline re-exec, ETag, 304, store-keyed, distinct
   SKU distinct key, invalid/non-public unchanged, errors no-store, product invalidation,
   MSI invalidation; existing suites green.

## 6. Risk

Stale availability if some stock mutation path skips both events — mitigated by matching
core's own `module-inventory-cache` trigger (the same signal core uses to drop FPC), plus
lifetime bounded by config (default 3600s, admin-tunable to 0 = disabled).
