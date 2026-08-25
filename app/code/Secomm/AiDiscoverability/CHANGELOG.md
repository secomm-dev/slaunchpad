# Changelog

All notable changes to `Secomm_AiDiscoverability` are documented here.

## [1.2.1] - 2026-08-25

Store-scoped category selector fix. Spec: `SPEC-TASK-S7MFCT`.

### Fixed
- Admin category multiselect now scopes to the configuration section's scope
  (core `website`/`store` request-param mechanism): store-view scope shows only
  that store group's root tree; the tree root itself (e.g. "Default Category")
  and the global root are never selectable; inactive categories stay excluded.
- Website scope: one distinct group root → that tree; multiple distinct roots →
  labeled union of that website's trees only (no foreign websites, no silent
  tree pick). Default scope: labeled union of all trees.
- Duplicate category names disambiguated with breadcrumb labels
  (`Women > Accessories`), store-group name prefix when several trees are mixed;
  fallback `[ID: n]` for unnamed categories. Labels built from the same single
  collection (`path` + id→name map) — no N+1.

## [1.2.0] - 2026-08-25

AI Discovery → Commerce endpoint integration. Spec: `SPEC-TASK-7FBHHC`.

### Added
- `## Machine-readable Commerce` llms.txt section advertising the `Secomm_AiCommerce`
  read-only surface (`/ai/store`, `/ai/catalog/search`, `/ai/categories`,
  `/ai/products/{sku}` route template) — rendered only when AiCommerce is present
  AND enabled for the store view. Soft seam (`ModuleList` + config flag): no
  AiCommerce class reference, no endpoint execution, no catalog load.
- `admin_system_config_changed_section_seocomm_ai_commerce` observer dropping the
  llms.txt cache when AiCommerce config changes.
- `EligibilityChecker::isEligibleCmsIdentifier()` — system utility CMS pages
  (`enable-cookies`, `no-route`) are never emitted even if selected by an admin.
- Plain-entry formatter form `- Label: url` for route templates (`{sku}` placeholder).

### Fixed
- README configuration table paths corrected to the runtime values:
  `urls/cms_pages`, `urls/categories`, `urls/include_sitemap_refs`.

## [1.1.0] - 2026-08-24

LC-30.1 llms.txt v2 conformance + store metadata. Spec delta: `SPEC-TASK-0X552E` §12.

### Changed
- Link entries now use the llms.txt v2 Markdown hyperlink form `- [Label](url)` with an
  optional `: Description` suffix (was `- [Label]: url`).
- H1 site title resolved via public fallback chain: `general/site_title` →
  `general/store_information/name` → internal store view name (last resort only).
- Added `Currency:` metadata line (after `Locale:`), read from store-scoped
  `currency/options/default` for determinism (not the visitor-switchable currency).

### Added
- `Site / Brand Title` admin field (store-view scoped) with bilingual `en_US`/`vi_VN` labels.
- Optional entry descriptions sourced only from existing CMS page / category
  `meta_description` (sanitized, 200-char bound; never generated; absent → no suffix).

## [1.0.0] - 2026-08-24

Initial LC-30 AI Discoverability (AIEO) baseline. Spec: `SPEC-TASK-0X552E` (v2).

### Added
- Root `/llms.txt` endpoint (custom `RouterList` router, sortOrder 20): GET 200 deterministic
  plain-text body, HEAD same headers with empty body, `Cache-Control: public, max-age=3600`,
  `ETag` + `If-None-Match` → 304, explicit 404 when disabled for the store view.
- Store-view scoped admin configuration under Secomm → AI Discoverability (enable/summary/
  priority paths/CMS pages/categories/sitemap refs/cache lifetime/max URLs) with ACL.
- Deterministic curated generator: Priority Pages / Collections / Pages / Sitemap sections,
  cross-section dedupe, label sort, global URL bound.
- Eligibility checker excluding non-public surfaces (admin, api, graphql, checkout, customer,
  search, llms, robots.txt, query/fragment URLs).
- Soft Mirasvit SEO integration: noindex rule reuse (`seo/general/noindex_pages2`), trailing
  slash policy (`seo/url/trailing_slash`), oldest-non-redirect-rewrite category canonical —
  zero Mirasvit class references; module degrades gracefully when Mirasvit is absent.
- Sitemap file references reused from `Magento_Sitemap` (no new sitemap generation).
- `rel="describedby"` link in frontend `<head>` when enabled.
- Store-scoped response cache (`seocomm_llms_txt_store_{id}`) with targeted invalidation
  observers (module config, Mirasvit seo config, CMS page, category, sitemap saves).
- ACL-protected admin preview sharing the same generator (cache-bypassing fresh seam).
- 21 unit tests (formatter, eligibility, collector, SEO policy, canonical policy).
