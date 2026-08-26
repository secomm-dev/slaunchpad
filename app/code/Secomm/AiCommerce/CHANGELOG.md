# Changelog

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
