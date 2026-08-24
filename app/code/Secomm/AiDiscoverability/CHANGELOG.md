# Changelog

All notable changes to `Secomm_AiDiscoverability` are documented here.

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
