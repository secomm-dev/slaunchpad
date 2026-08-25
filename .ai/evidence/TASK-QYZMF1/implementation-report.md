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

## 6. Checklist sections reviewed

constitution §1, §2, §9, §10, §11; checklist §0, §1, §2, §9, §12 (docblocks complete,
strict types, no OM, no raw SQL, tests via createMock/builder only).
