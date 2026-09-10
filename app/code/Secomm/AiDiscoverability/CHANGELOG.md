# Changelog

All notable changes to `Secomm_AiDiscoverability` are documented here.

## [1.6.1] - 2026-09-10

llms.txt metadata contract alignment: the configured Brand / Site Summary
renders exactly once under Store Summary (no duplicated top-level blockquote),
and the Store Information purpose line states the actual `/ai/store` V1
response contract.

### Fixed
- Removed the duplicated Brand / Site Summary blockquote: the summary used to
  render twice (as `> summary` under the H1 — with a site-title fallback —
  and again under `## Store Summary`) although both originate from the same
  `general/brand_summary` config field. `LlmsTxtFormatter::format()` no longer
  accepts or renders a summary block (signature is now
  `format($siteName, $locale, $sections, $currency)`); `LlmsTxtGenerator`
  renders the configured summary exactly once as the Store Summary prose
  section and omits the section entirely when the field is empty — no
  fallback, no fabricated text. Site-title fallback behavior for the H1 is
  unchanged.

### Changed
- `/ai/store` purpose line in llms.txt now matches the StoreDto V1 allowlist
  (`store_code`, `locale`, `currency`, `base_url`): "Store metadata: store
  code, locale, currency and base URL." (was: "Store metadata, locale,
  currency and supported public catalog context."). Documentation only — the
  endpoint response contract is unchanged.
- `Brand / Site Summary` admin field comment (+ en_US/vi_VN translations) now
  documents the rendered-once-under-Store-Summary behavior.

## [1.6.0] - 2026-09-08

Product Search usage guidance in llms.txt. Companion to the AiCommerce 1.2.1
search fixes (BUG-7M4KQX / BUG-K8T3WR / BUG-V2N9DL): after those fixes were
live-verified, the `### Product Search` endpoint block now documents the
query contract an agent needs, so it never has to guess parameter names.

### Added
- `usage` lines under `### Product Search` (`CommerceEndpointsSource::getSearchUsageLines`),
  advertising the live-proven read-only contract: `q`, `category` (ids from
  the Categories endpoint, unknown id → 400 invalid_parameter),
  `price_min`/`price_max` (both bounds apply, each works alone), the 5-value
  `sort` allowlist (price sorts follow the catalog price index), conditional
  `filter[ATTRIBUTE]=OPTION_ID` (attribute codes read store-scoped from the
  AiCommerce filter allowlist config; omitted entirely when the allowlist is
  empty), `page`/`page_size` (defaults 1/20, bound read from AiCommerce
  `max_page_size` config), `store` selector, and the 400 invalid_parameter
  contract for unknown parameter names. Two example GET URLs are built from
  the effective endpoint URL (never a hardcoded domain/path/store).
- `LlmsTxtFormatter` detail-entry `usage` rendering after the `Purpose:` line,
  sanitized (HTML/control chars stripped) but with literal square brackets —
  the documented `filter[...]` syntax stays copy-pastable.

## [1.5.0] - 2026-08-26

Dedicated cache type + configurable section titles + configurable AI endpoint base path. Spec: `SPEC-TASK-HL3WQD`.

### Added
- Module-owned cache type `secomm_ai_discoverability` (`etc/cache.xml` +
  `Model/Cache/Type`, core TagScope pattern): visible in `cache:status`,
  cleanable via `cache:clean secomm_ai_discoverability`; disabling the type
  disables llms.txt caching. Caching/invalidation now run through the type
  (`LlmsTxtProvider`, `Model/InvalidateCache` — Zend-style TagScope clean
  contract, distinct from the Proxy contract fixed in 1.4.1).
- Admin-configurable llms.txt section titles (Secomm → AI Discoverability →
  Section Titles, store-view scoped): all `##` headings plus the `###`
  commerce endpoint headings. Defaults reproduce the previous output
  byte-identically; empty values fall back to defaults.
- llms.txt now advertises the configured AiCommerce endpoint base path
  (default `ai`) instead of a hardcoded `/ai/` prefix.

### Changed
- `SPEC-BUG-AIDL-CINV1` family renamed to `SPEC-CHANGE-AIDL-CINV1` /
  `CHANGE-AIDL-CINV1` by the BUG-provenance audit (implementation-originated
  artifact; rule encoded in `.ai/AGENTS.md` §8.7). History preserved.

## [1.4.1] - 2026-08-25

Fix: llms.txt tag invalidation was a runtime no-op. Spec: `SPEC-CHANGE-AIDL-CINV1` (renamed from SPEC-BUG-AIDL-CINV1 by the BUG-provenance audit — implementation-originated artifact, not an independent QA finding).

### Fixed
- `Model/InvalidateCache` passed Zend-style arguments to
  `CacheInterface::clean(CLEANING_MODE_MATCHING_TAG, [tags])`; the runtime
  `App\Cache\Proxy` contract is `clean(array $tags)` — the mode string was swallowed as a
  tag and every invalidation (config ×3, CMS page, category, sitemap observers) silently
  no-opped, bounded only by the ~3600s TTL. Now calls `clean([tag])` with the plain tags
  array. Discovered and proven during TASK-AIC-PDC1 (redis MONITOR).

## [1.4.0] - 2026-08-25

llms.txt V1.1 — richer business context + agent guidance. Spec: `SPEC-TASK-QYZMF1`.

### Added
- `## Store Summary` section from the configured Brand / Site Summary (omitted when
  blank — never fabricated).
- `## Agent Guidance` + `## Commerce Limitations` deterministic module-generated
  sections (rendered only when the machine-readable commerce surface is available for
  the store view; no numeric rate limits; no capability beyond the read-only surface).
- Purpose lines for the machine-readable commerce endpoints, rendered as
  `### <endpoint>` + `GET <url>` + `Purpose: …` blocks; the `{sku}` route template
  stays plain text (never a Markdown link).

### Changed
- Section names: `Collections` → `Featured Collections`, `Pages` → `Key Pages`,
  sitemap entry label → `XML Sitemap`.
- Category descriptions: deterministic precedence `meta_description` → sanitized
  plain-text `description` attribute (HTML stripped, 240-char bound) → omitted.
- Link description bound raised 200 → 240 characters.

## [1.3.0] - 2026-08-25

Hierarchical category tree selector (Product Edit Categories UX). Spec: `SPEC-TASK-5TGJ7V`.

### Changed
- The Categories / Collections config field now uses the exact Product Edit
  Categories interaction: core `Magento_Ui/js/form/element/ui-select` (template
  `ui/grid/filters/elements/ui-select`) — selected categories as removable
  chips, dropdown with searchable checkbox hierarchy, expand/collapse, Done
  close action. Instantiated standalone via core `Magento_Ui/js/core/app`.
  Duplicate names are disambiguated by hierarchy context.
- Config value remains comma-separated category entity IDs — existing saved
  values load as selected chips; llms.txt generation unchanged.
- Scope rules preserved: store view → that group's tree; website → own trees
  (distinct roots shown as labeled groups); default → labeled union. Root
  categories are never selectable; inactive categories never appear; foreign
  trees are excluded by the stored-path filter (SPEC-TASK-QQMVY4 invariant).

### Removed
- `Model\Config\Source\Categories` flat multiselect source model (replaced by
  `Model\Config\CategoryTreeProvider` + `Block\Adminhtml\System\Config\CategoryTree`).

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
