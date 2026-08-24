# Changelog

## 1.0.0 (2026-08-24)

- Initial implementation of the LA-22 read-only agent commerce facade:
  `/ai/store`, `/ai/catalog/search`, `/ai/products/{sku}`, `/ai/categories`
  (GET/HEAD only, `?store=` store context, deterministic error envelope,
  per-store cache + tag invalidation, admin config, en_US/vi_VN i18n).
