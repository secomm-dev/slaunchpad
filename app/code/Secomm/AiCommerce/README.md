# Secomm_AiCommerce — AI Commerce Read Layer (LA-22)

Bounded anonymous **read-only** commerce facade for AI agents. The module is a
SECURITY BOUNDARY: it never proxies GraphQL documents, never exposes raw
`/graphql`, and delegates all search/pricing/inventory/URL behavior to
Magento native services (Elasticsuite-preferenced storefront search
collection, PriceInfo, MSI salability APIs, url_rewrite finder).

Spec: `.ai/specs/SPEC-TASK-QV3R7T-la-22-ai-commerce-read-layer.md`
Plan: `.ai/plans/TASK-QV3R7T-implementation-plan.md`

## Public V1 surface (GET/HEAD only; other verbs → 405 envelope)

| Route | Purpose |
|-------|---------|
| `/ai/store` | store context: `store_code`, `locale`, `currency`, `base_url` |
| `/ai/catalog/search` | bounded storefront search (q/category/price/page/sort/allowlisted filters) |
| `/ai/products/{sku}` | public product detail (fixed DTO allowlist) |
| `/ai/categories` | active store category tree (one collection load) |

Base path: configurable per store view — `Secomm → AI Commerce Read Layer →
General → AI Read Endpoint Path` (`seocomm_ai_commerce/general/endpoint_path`,
default `ai`; surrounding slashes stripped on save, invalid values fall back
to `ai`). The table above shows the default paths; changing the path fully
retires the old URLs (the module declares no standard frontName route — the
custom router is the single routing authority). `llms.txt` advertises the
effective configured URLs.

Store selection: `?store=<store_code>` ONLY (no headers, no cookies).
Missing → documented default store; invalid/inactive → 400 `invalid_store`.
Responses make the store context explicit via `/ai/store` and `store` URL
identity — no `Vary` header is needed because the store is part of the URL.

## Security boundary (safe without WAF)

- GET/HEAD-only router; POST/PUT/PATCH/DELETE get the 405 envelope and never
  execute commerce logic
- no GraphQL document input, no SearchCriteria injection, no raw field names
- fixed input allowlist; unknown params, oversized q (>128), oversized query
  string (>512), >4 filters, page/page_size beyond caps → 400
- no customer tokens, no session, no cookies affect responses; Authorization
  headers are ignored
- availability is status-only (`in_stock|out_of_stock`) — quantities are
  never emitted (MSI `IsProductSalableInterface`/`AreProductsSalableInterface`)
- deterministic error envelope `{error:{code,message}}`; no stack traces,
  paths, class names or engine details
- per-store `enabled` flag (config default 1 since 1.2.0; 404 when disabled)

## Cache

`Cache-Control: public, max-age` + `ETag`/304; internal per-store+query cache
key with tags `secomm_aic`, `secomm_aic_store_<id>` for ALL four read endpoints
including `/ai/products/{sku}` (route `product`, normalized SKU in the key;
SPEC-TASK-AIC-PDC1). Product/category/config observers invalidate the whole
module tag (a product change can make a previously absent product enter search
results — correctness first). MSI stock/salability changes never fire
`catalog_product_save_*`; the `clean_cache_by_tags` observer (product cache-tag
identities — the same signal core `module-inventory-cache` uses to drop FPC)
covers them. NOTE: invalidation calls `CacheInterface::clean([tag])` — the
runtime `App\Cache\Proxy` contract — not the Zend-style `clean($mode, $tags)`,
which silently no-ops.

## Known limitations

- Mirasvit conditional (request-bound) canonical rules are not reproduced
  offline; `canonical_url` comes from the oldest `redirect_type=0` url_rewrite
  row (LC-30-proven rule) and is `null` when no rewrite can be proven.
- Configurable option values expose the value index; per-store option labels
  are the attribute label of the option itself.
- Search availability uses `AreProductsSalableInterface`, which in 2.4.8
  iterates `IsProductSalableInterface` internally — one facade call for the
  page, but not one SQL batch.

## LC-30 relationship

`/llms.txt` integration is delivered on the Secomm_AiDiscoverability side
(SPEC-TASK-7FBHHC): when this module is enabled for a store view, llms.txt
advertises the read-only `/ai/*` surface in a `## Machine-readable Commerce`
section. No AiCommerce behavior depends on it.
