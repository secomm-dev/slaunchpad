# TASK-QYZMF1 — llms.txt V1.1 Evidence

- Spec: `.ai/specs/SPEC-TASK-QYZMF1-ai-discoverability-llms-v1-1.md` (MINI, Mode C)
- Plan: `.ai/plans/TASK-QYZMF1-implementation-plan.md`
- Branch: `task/ai-discoverability-llms-v1-1` (base `0719e4f2`)
- Date: 2026-08-25

## 1. Files changed

| File | Change |
|---|---|
| `Service/LlmsTxtFormatter.php` | prose entries (`{prose: [...]}`), commerce detail entries (`### Label` / `GET url` / `Purpose: …`), link description bound 200→240 |
| `Service/LlmsTxtGenerator.php` | V1.1 section order; Store Summary (brand_summary only); Agent Guidance + Commerce Limitations prose (only when commerce entries non-empty); commerce entries wrapped as detail; dedup/bound loop reads detail URLs; commerce availability from bounded section |
| `Service/Source/CategoriesSource.php` | description precedence meta_description → sanitized bounded (240) description → omit; defensive attribute reader (magic getter OR custom attributes — `getDescription` is not a declared Category method) |
| `Service/Source/CommerceEndpointsSource.php` | factual `purpose` per endpoint (no behavior change) |
| `Service/Source/SitemapRefsSource.php` | label `XML Sitemap` |
| `Test/Unit/Service/LlmsTxtFormatterTest.php` | prose/detail rendering, bound 240 |
| `Test/Unit/Service/LlmsTxtGeneratorTest.php` | V1.1 structure, commerce on/off, forbidden-capability audit, Store Summary omit |
| `Test/Unit/Service/Source/CategoriesSourceTest.php` | description precedence/fallback/bound/omit |
| `Test/Unit/Service/Source/CommerceEndpointsSourceTest.php` | purpose fields |
| `README.md`, `CHANGELOG.md` (1.4.0) | V1.1 documentation |

## 2. Runtime proof — vi_vn (store 3), fresh generation

With temporary UAT data (brand_summary + site_title store 3; cat 3 meta_description;
cat 4 HTML description — all removed after capture, verified 0 rows):

```
# Vietnam Store (UAT)
> Temporary UAT store summary for vi_vn — do not keep.

Locale: vi_VN
Currency: VND

## Store Summary
Temporary UAT store summary for vi_vn — do not keep.

## Agent Guidance
This site provides public machine-readable commerce endpoints for catalog discovery.
Use the machine-readable endpoints below when structured catalog data is sufficient instead of scraping storefront HTML.
The current machine-readable interface is read-only.
Do not use these endpoints for:
- cart creation / - checkout / - customer accounts / - orders / - addresses / - payments
Respect HTTP rate limits and cache responses where appropriate.

## Priority Pages
- [Vietnam Store View](https://webhook.thanhaloha.io.vn)

## Featured Collections
- [Fitness Equipment](https://webhook.thanhaloha.io.vn/gear/fitness-equipment): Temporary UAT description with HTML for Fitness Equipment. Should render as safe plain text only.
- [Gear](https://webhook.thanhaloha.io.vn/gear): Temporary UAT meta description for Gear.

## Key Pages
- [Home page](https://webhook.thanhaloha.io.vn/home)
- [Privacy and Cookie Policy](https://webhook.thanhaloha.io.vn/privacy-policy-cookie-restriction-mode)

## Machine-readable Commerce

### Store Information
GET https://webhook.thanhaloha.io.vn/ai/store?store=vi_vn
Purpose: Store metadata, locale, currency and supported public catalog context.

### Product Search
GET https://webhook.thanhaloha.io.vn/ai/catalog/search?store=vi_vn
Purpose: Search public products using the bounded AI Commerce catalog facade.

### Categories
GET https://webhook.thanhaloha.io.vn/ai/categories?store=vi_vn
Purpose: Browse public category data for this store view.

### Product Detail
GET https://webhook.thanhaloha.io.vn/ai/products/{sku}?store=vi_vn
Purpose: Retrieve public product information for a known SKU.

## Commerce Limitations
The current interface does NOT expose: (cart creation, checkout, payment,
customer account data, order data, address data)
Transactional operations must use the storefront.
```

Proven: title, blockquote, Locale/Currency, Store Summary, Agent Guidance (no numeric
rate limits), Featured Collections with BOTH description sources (meta_description +
HTML description stripped to safe bounded plain text), Key Pages, GET+Purpose commerce
blocks ({sku} template not a link), Commerce Limitations. No UCP/MCP/WebMCP/checkout-
mutation strings anywhere (unit-audited too).

## 3. Second-store isolation — default (store 1)

Fresh generation store 1: own title `Secomm Launchpad`, NO vi_vn UAT summary
(no leak), own collections (More/Outdoor — store-scope curated config), commerce
URLs `?store=default`. Store-scoped metadata does not leak across store views.

## 4. Cleanup

Temporary UAT rows deleted and verified 0 (config store 3 brand_summary/site_title;
cat 3 meta_description; cat 4 description); /tmp proof scripts removed; cache flushed;
post-cleanup vi_vn output returns to inherited title `Secomm Launchpad`.

## 5. Validation

| Check | Result |
|---|---|
| AiDiscoverability full suite | 62 tests / 153 assertions, OK (pre-existing allure warning) |
| AiCommerce regression suite | 51 tests / 83 assertions, OK |
| php -l (changed files) | clean |
| PHPCS Magento2 (changed files) | 0 errors / 0 warnings |
| setup:di:compile | not run — no DI/constructor changes |
| project-ai-validate --check-specs | VALID (0 FAIL, 0 WARN) |
| git diff --check / app/etc/config.php vs base | clean / unchanged |

## 6. Final verification round (2026-08-25, pre-merge)

### 6.1 Sitemap runtime proof (vi_vn / store 3)

- `include_sitemap_refs` effective for store 3 (websites 2 / default 0 chain): **1 (enabled)**.
- Core `sitemap` table: exists, **0 rows for every store** (no Magento sitemap generated anywhere);
  no sitemap file in `pub/`. Mirasvit SeoSitemap (`mst_seo_sitemap_provider`) is a separate
  mechanism the module intentionally does not read.
- `SitemapRefsSource` queries that table and returns `[]` when empty → `## Sitemap` omitted.
- Classification: **B — no sitemap exists** (config enabled; source intact; no regression;
  no hardcoding of `/sitemap.xml`). **No code change.** When an admin generates a sitemap for
  the store in Marketing → SEO → Sitemap, the section will appear with the real filename/path.

### 6.2 CMS meta_description proof (real data)

- Selected config `urls/cms_pages` = `3,2,4`. Eligibility drops `enable-cookies` (system page);
  emitted pages are Home (2) and Privacy (4) — **neither has a `meta_description`** in
  `cms_page` (empty/NULL), so link-only entries are the correct current behavior.
  Only `no-route` (page 1, not selected + eligibility-excluded) carries a meta description.
- Positive proof (temporary UAT, fully cleaned + verified): setting
  `cms_page.meta_description` on Home rendered
  `- [Home page](.../home): Temporary UAT CMS meta description for Home page.`
  (after cache flush) — `CmsPagesSource` → formatter path works. **No code change.**

### 6.3 Post-cleanup real vi_vn output

One leftover UAT row was found and removed in this round: `catalog_category_entity_text`
category 3 `meta_description` at **store 0** (default scope) had been missed by the earlier
store-3-scope cleanup; deleting it and flushing cache restored the clean state. Verified
0 rows matching "Temporary" across config / cms_page / catalog category text tables.

Fresh output now (cache flushed, `?store=vi_vn`):

```
# Secomm Launchpad
> Secomm Launchpad

Locale: vi_VN
Currency: VND

## Agent Guidance
This site provides public machine-readable commerce endpoints for catalog discovery.
Use the machine-readable endpoints below when structured catalog data is sufficient instead of scraping storefront HTML.
The current machine-readable interface is read-only.
Do not use these endpoints for:
- cart creation
- checkout
- customer accounts
- orders
- addresses
- payments
Respect HTTP rate limits and cache responses where appropriate.

## Priority Pages
- [Vietnam Store View](https://webhook.thanhaloha.io.vn)

## Featured Collections
- [Fitness Equipment](https://webhook.thanhaloha.io.vn/gear/fitness-equipment)
- [Gear](https://webhook.thanhaloha.io.vn/gear)

## Key Pages
- [Home page](https://webhook.thanhaloha.io.vn/home)
- [Privacy and Cookie Policy](https://webhook.thanhaloha.io.vn/privacy-policy-cookie-restriction-mode)

## Machine-readable Commerce

### Store Information
GET https://webhook.thanhaloha.io.vn/ai/store?store=vi_vn
Purpose: Store metadata, locale, currency and supported public catalog context.

### Product Search
GET https://webhook.thanhaloha.io.vn/ai/catalog/search?store=vi_vn
Purpose: Search public products using the bounded AI Commerce catalog facade.

### Categories
GET https://webhook.thanhaloha.io.vn/ai/categories?store=vi_vn
Purpose: Browse public category data for this store view.

### Product Detail
GET https://webhook.thanhaloha.io.vn/ai/products/{sku}?store=vi_vn
Purpose: Retrieve public product information for a known SKU.

## Commerce Limitations
The current interface does NOT expose:
- cart creation
- checkout
- payment
- customer account data
- order data
- address data
Transactional operations must use the storefront.
```

Checks: real title `Secomm Launchpad`; blockquote is the site-title fallback (brand_summary
blank → `## Store Summary` correctly omitted); Agent Guidance / Priority / Featured
Collections (link-only after cleanup) / Key Pages / commerce GET+Purpose blocks /
Commerce Limitations all present; `## Sitemap` absent (reason B); no UAT strings; no
UCP/MCP/WebMCP/checkout-mutation capability claims.

### 6.4 Duplication check — brand summary

When `brand_summary` IS configured, the same text renders twice: the `>` blockquote
(llms.txt v2 header convention) and `## Store Summary`. **YES, duplicated by design** —
the spec's target structure explicitly includes both, the blockquote is the standard
llms.txt summary position while Store Summary is a dedicated scannable section, and the
text is merchant-authored (not generated). Report-only; no change. In the current clean
state the blockquote shows the site title (fallback), so no duplication is live.

### 6.5 Verification-round deltas

Evidence-only update — **no production code changed**; no tests added or modified.

## 7. Checklist sections reviewed

constitution §1, §2, §9, §10, §11; checklist §0, §1, §2, §9, §12 (docblocks complete,
strict types, no OM, no raw SQL, tests via createMock/builder only).
