# Changelog

All notable changes to `Secomm_AiDiscoverability` are documented here.

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
