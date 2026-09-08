# Changelog

## 1.2.1 (2026-09-08)

Search correctness fixes from the live API audit (`/ai/catalog/search`).
Bugs: `BUG-7M4KQX`, `BUG-K8T3WR`, `BUG-V2N9DL`.

### Fixed
- `sort=price_asc` / `price_desc` no longer crash with 500: the service pins
  the guest price context via `addPriceData(Group::NOT_LOGGED_IN_ID, website)`
  before ordering (the Elasticsuite collection reads
  `_productLimitationFilters['customer_group_id']` unguarded when building the
  nested price sort).
- `price_min` + `price_max` combined are now issued as ONE
  `addFieldToFilter('price', ['gteq' => .., 'lteq' => ..])` condition — the
  collection stores filters keyed by mapped field name, so two separate calls
  silently overwrote the lower bound.
- `category=<id>` now actually constrains the engine query through
  `addCategoryFilter()` (the SQL-oriented `addCategoriesFilter()` is silently
  ignored by the Elasticsuite collection). A nonexistent category resolves to
  400 `invalid_parameter` instead of silently returning the full catalog.

## 1.2.0 (2026-08-26)

Configurable AI read endpoint base path. Spec: `SPEC-TASK-HL3WQD`.

### Added
- `seocomm_ai_commerce/general/endpoint_path` (store-view scoped, default `ai`):
  the URL base path of the read endpoints. Canonicalized at save time
  (`Model/Config/Backend/EndpointPath` delegating to
  `Model\Config::normalizeEndpointPath()` — the single normalization authority);
  invalid values (empty, bare `/`, query, fragment, protocol URLs, traversal,
  spaces, other characters) fall back to `ai`, so routing never breaks.

### Changed
- `Controller/Router` resolves the base path from config and is now the single
  routing authority: the standard frontName route (`etc/frontend/routes.xml`)
  is removed, so changing the path fully retires the old `/ai/*` URLs instead
  of leaving them live behind the standard router. Default-scope
  `general/enabled` default is now `1`; deployments with a saved value
  (including this one) are unaffected.
- llms.txt (`Secomm_AiDiscoverability`) advertises the effective configured
  URLs.

## 1.1.0 (2026-08-25)

Product-detail response cache + working tag invalidation. Spec: `SPEC-TASK-AIC-PDC1`
(audit P1.1 follow-up).

### Added
- `/ai/products/{sku}` now uses the shared per-store `ResponseCache` (route `product`,
  normalized SKU in the key) with the configured cache lifetime: warm hits skip the
  ProductRepository/MSI/pricing pipeline; responses carry `Cache-Control: public` + `ETag`
  with If-None-Match → 304. Errors stay no-store and uncached.
- MSI stock invalidation observer on the Magento-native `clean_cache_by_tags` event
  (product cache-tag identities): stock/salability changes — which never fire
  `catalog_product_save_*` — drop the module response cache, same as product saves.

### Fixed
- Tag invalidation was a silent runtime no-op: the runtime `CacheInterface`
  (`App\Cache\Proxy`) `clean($tags)` contract swallowed the Zend-style mode string as a
  tag (proven via redis MONITOR: `SINTER zc:ti:798_MATCHINGTAG`). `InvalidateCache` now
  passes the plain tags array. All existing observers (product/category/config) become
  effective with this fix.

## 1.0.0 (2026-08-24)

- Initial implementation of the LA-22 read-only agent commerce facade:
  `/ai/store`, `/ai/catalog/search`, `/ai/products/{sku}`, `/ai/categories`
  (GET/HEAD only, `?store=` store context, deterministic error envelope,
  per-store cache + tag invalidation, admin config, en_US/vi_VN i18n).
