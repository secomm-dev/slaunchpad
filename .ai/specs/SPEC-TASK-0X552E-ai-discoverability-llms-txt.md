# [SLP][TASK-0X552E] LC-30 — AI Discoverability / AIEO Baseline (`/llms.txt`)

> **External ref**: LC-30 · **Mode**: A (spec-first, architecture touch — frontend route + SEO integration)
> **Status**: DRAFT — chờ TL review. **Không có production code trong task này.**
> **Scope guard**: curated discovery, KHÔNG phải sitemap thứ hai; không llms-full.txt, không MCP/UCP/ACP, không chatbot, không commerce API, không embeddings, không AI-generated content, không Schema.org engine, không Cloudflare.

---

## 0. Repository Reality Audit (A–H)

### A. Canonical / SEO

- **Hiện trạng**: SEO stack đang là **Mirasvit SEO suite đầy đủ** (bật trong `app/etc/config.php`): `Mirasvit_Seo`, `SeoMarkup`, `SeoSitemap`, `SeoToolbar`, `SeoAi`, `SeoAudit`, `SeoAutolink`, `SeoContent`, `SeoFilter`, `SeoNavigation` + compat `Hyva_MirasvitSeo*` (shim layoutのみ).
- Canonical URL do Mirasvit sinh: `app/code/Mirasvit/Seo/Observer/Canonical.php` (`getCanonicalUrl()` → `addLinkCanonical()`), phụ trợ bởi `Mirasvit\Seo\Service\CanonicalRewrite\CanonicalRewriteService` và `CanonicalLayeredService`.
- **Không có SEO override nào của Secomm**; **không tồn tại LC-21** hay bất kỳ spec/code SEO/llms.txt nào trong `.ai/` (đã grep toàn bộ — "llms" chỉ xuất hiện trong README marketing của Magefan).
- `Mirasvit_SeoAi` là AI meta-content generation (meta auto-fix via `Service/MetaAutoFixService.php`, `Cron/MetaAutoFixCron.php`) — **không phải llms.txt, không collision**.
- **Khả năng tái sử dụng**: logic indexability/noindex của Mirasvit là **pattern-rule config** (`Mirasvit\Seo\Model\Config::getNoindexPages()`, path `seo/general/noindex_pages2`) — áp cho pages theo pattern, không theo entity. LC-30 **không phụ thuộc** Mirasvit runtime; chỉ cần tôn trọng rằng canonical thực tế của storefront do Mirasvit quyết định.
- **Gap**: không có service nào expose "canonical public URL per store" dưới dạng API sạch cho một endpoint ngoài page-render. LC-30 tự resolve qua `UrlFinderInterface` (mục E).
- **Khuyến nghị**: LC-30 dùng core `UrlFinderInterface` + store-scoped base URL; không plugin vào Mirasvit.

### B. Robots.txt

- **Hiện trạng**: core `Magento_Robots` (route `robots` qua `vendor/magento/module-robots/etc/frontend/routes.xml` + `Controller/Router.php` sortOrder 10); nội dung từ `vendor/magento/module-robots/Model/Robots.php` + custom instructions ở config `design/search_engine_robots/custom_instructions`.
- Mirasvit chỉ chạm robots.txt để append dòng Sitemap: `app/code/Mirasvit/SeoSitemap/Model/Config/Backend/Robots.php` (toggle `sitemap/search_engines/submission_robots`) — **ghi vào cùng config value** `custom_instructions`.
- Không có plugin/preference nào trên class robots trong `app/code`.
- **Khả năng tái sử dụng / seam**: plugin frontend `after` trên `Magento\Robots\Model\Robots::toOptionArray()`-level hoặc tốt hơn — **append dòng `Link`/comment qua plugin trên `Magento\Robots\Block\Data::_toHtml()`** không cần thiết; thực tế **không cần sửa robots.txt cho chuẩn llms.txt** (discovery signal chuẩn là HTTP `Link: <...>; rel="describedby"` trên storefront response hoặc link head). robots.txt chỉ cần **không chặn** `/llms.txt` — mặc định không chặn.
- **Xác nhận**: KHÔNG thay thế renderer robots.txt. Nếu muốn mention llms.txt trong robots.txt (tùy chọn, SHOULD), dùng plugin after trên `Magento\Robots\Model\Robots` appends một dòng comment — phải concatenate, không được rewrite output.

### C. Sitemap

- **Hiện trạng**: `Mirasvit_SeoSitemap` active, preference lên `Magento\Sitemap\Model\Sitemap` (`app/code/Mirasvit/SeoSitemap/etc/di.xml`); generation qua cron core `sitemap_generate` (`vendor/magento/module-sitemap/etc/crontab.xml`), file ghi ra filesystem per store; config paths `seositemap/*` + core `sitemap/*`.
- **Tái sử dụng**: LC-30 **tham chiếu** sitemap URL (đọc config `sitemap/*/filename` + `sitemap/*/path` theo store, hoặc từ sitemap grid `sitemap_sitemap` table qua `Magento\Sitemap\Model\SitemapManager`) — **không tự generate, không duplicate logic**.
- **Gap**: sitemap config là per-website/store qua admin grid chứ không phải scope-config thuần; cần đọc qua `SitemapManager` (đã có sẵn, dùng được).

### D. CMS / Category Indexability

- **CMS**: `cms_page.is_active` (`vendor/magento/module-cms/etc/db_schema.xml`); store visibility qua `cms_page_store` (store `0` = all); resolve per store qua `Magento\Cms\Model\GetPageByIdentifier`; collection có `addStoreFilter()`. **Không có cột `meta_robots`** cho CMS page trong 2.4.8 core.
- **Category**: `is_active` là EAV attribute; form admin `vendor/magento/module-catalog/view/adminhtml/ui_component/category_form.xml` chỉ có `meta_title/keywords/description` — **core 2.4.8 KHÔNG có category meta_robots attribute** (đã xác minh). Mirasvit cũng không thêm attribute meta_robots cho category (chỉ thêm `m_seo_canonical` cho product qua `DataPatch108.php`); noindex của Mirasvit là pattern-rule config (`seo/general/noindex_pages2`).
- **Phân biệt proven vs proposed**:
  - *Proven*: enabled/disabled, store visibility, canonical URL rewrite, redirect rewrites.
  - *Proposed (policy)*: "noindex entity" — Magento không expose flag per CMS page/category. LC-30 xử lý bằng (1) tôn trọng Mirasvit noindex **patterns** nếu muốn (đọc `seo/general/noindex_pages2` — SHOULD, vì đây là nguồn noindex thật duy nhất trên site), và (2) admin config của LC-30 cho merchant **blacklist thêm URL patterns** (MUST, rẻ). Không erfunden per-entity noindex flag.

### E. URL Rewrite / Public URL Eligibility

- Resolver chuẩn: `Magento\UrlRewrite\Model\UrlFinderInterface::findOneByData(['entity_type' => 'category', 'entity_id' => X, 'store_id' => Y, 'is_autogenerated' => 1])` → `request_path` chính là canonical path của entity trong store đó.
- Redirect rewrite = `redirect_type != 0` (constants trong `Service/V1/Data/UrlRewrite.php`; 301/302 trong `Magento\UrlRewrite\Model\OptionProvider`) → **reject**.
- Query/filter/sort variants: llms.txt chỉ emit bare canonical path (không query string) → loại trừ theo cấu trúc, không cần check runtime.
- Cross-store: luôn ràng buộc `store_id` trong mọi lookup + base URL từ `StoreManagerInterface::getStore($id)->getBaseUrl()` → không thể emit URL của store khác.
- CMS page canonical URL = `{storeBaseUrl}/{identifier}` (CMS không có url_rewrite rows mặc định).
- Ví dụ usage cùng pattern trong repo: `Mirasvit\SeoAudit\Service\Resolver\CatalogUrlRewriteResolver`, `Mirasvit\Seo\Service\AutoRedirect\TargetResolver`.

### F. Cache / Invalidation

- Pattern Secomm sẵn có: `Secomm/Ghtk/Model/RateCache.php` (constructor-injected `CacheInterface`), `Secomm/AddressDropdown/CustomerData/CityData.php` (`CacheFrontedPool` default + `TypeListInterface`). Không Secomm module nào khai báo custom cache type (`cache.xml`).
- **Thiết kế LC-30**: cache bằng `CacheInterface` (frontend `default`), key `SEOCMM_LLMS_{website?}_STORE_{storeId}` + custom **cache tag** `seocomm_llms` (tag-based clean qua `CacheInterface::clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, [...])`). Cache ID include store ID — store là cache boundary.
- **Invalidation**: observers trên các event sẵn có — `admin_system_config_changed_section_` (config sections liên quan), `cms_page_save_commit_after` / `cms_page_delete_commit_after`, `catalog_category_save_commit_after` / `catalog_category_delete_commit_after`, `clean_sitemap` — mỗi observer chỉ làm 1 việc: clean tag. **Lazy regeneration** ở request đầu sau invalid (không cron). Cron KHÔNG cần thiết — nguồn dữ liệu nhỏ (curated), regen < vài trăm ms.
- FPC: controller KHÔNG layout-render (plain text response) → không dính FPC, tự cache riêng là đủ.

### G. Admin Config / Preview

- Pattern sẵn có: `Secomm/Base/etc/adminhtml/system.xml` (shared tab `secomm`, sortOrder 5000) + mỗi module một section + `Model/Config.php` wrapper `ScopeConfigInterface`. Admin controller chuẩn: `Secomm/GhnAddressMapper/Controller/Adminhtml/Mapping/*`. **Chưa có preview controller nào** — LC-30 là cái đầu tiên, dùng pattern `Magento\Backend\App\Action` + ACL riêng.
- Preview dùng **cùng generator** (cùng service `LlmsTxtGenerator::generate(int $storeId): string`) — controller admin gọi trực tiếp generator, bỏ qua cache публичный response.

### H. Module Collision / Dependencies

| Khía cạnh | Tái sử dụng | Seam | Collision / Duplication risk |
|---|---|---|---|
| Mirasvit_Seo (canonical, noindex) | đọc `seo/general/noindex_pages2` (optional) | none | Thấp — LC-30 không render page, không chạm Canonical observer |
| Mirasvit_SeoSitemap | `SitemapManager` để tham chiếu sitemap URLs | none | Thấp — chỉ đọc config/DB, không generate |
| Magento_Robots | none (mặc định không chặn /llms.txt) | plugin after trên `Model\Robots` (optional comment line) | Thấp; **cẩn thận**: Mirasvit ghi `custom_instructions` — không được overwrite |
| Magento_Cms / Catalog | repository/collection + `UrlFinderInterface` | none | Thấp |
| Hyva theme | `<head>` thêm `rel="describedby"` link qua `default.xml` layout | layout update trong module (không sửa theme) | Thấp |
| Checkout/OSC | none | none | Không chạm — endpoint độc lập |

**Kết luận**: không có collision đáng kể; rủi ro lớn nhất là *duplicate* SEO logic (tránh bằng tham chiếu, không tái tạo) và *ghi đè* robots.txt (tránh bằng append-only plugin hoặc bỏ qua).

---

## 1. Scope Decision

| Item | Quyết định |
|---|---|
| `/llms.txt` store-view aware, curated (brand summary, priority URLs, CMS chọn lọc, categories chọn lọc, sitemap refs) | **MUST** |
| Eligibility: enabled + public + canonical + store-applicable; loại redirect rewrites, query variants, cross-store | **MUST** |
| Loại account/cart/checkout/wishlist/compare/search/internal bằng route-prefix blocklist + curated-only sources | **MUST** |
| `rel="describedby"` qua `<link>` trong head (layout, Hyva-compatible) | **MUST** |
| HTTP `Link` header thêm vào storefront responses | **DEFER** (link tag đủ cho baseline; header cần observation trên FPC/Varnish behavior) |
| robots.txt: đảm bảo không chặn + optional comment line qua plugin append-only | **SHOULD** (rẻ) |
| Tôn trọng Mirasvit noindex patterns (`seo/general/noindex_pages2`) cho category/CMS URLs | **SHOULD** (nguồn noindex thật duy nhất) |
| Admin config + preview cùng generator | **MUST** |
| Cache tag + targeted invalidation, lazy regen | **MUST** |
| Cron regeneration | **OUT** (không cần bằng chứng) |
| llms-full.txt, markdown per-page, MCP/UCP/ACP, chatbot, commerce API, embeddings, AI content, Schema engine, Cloudflare | **OUT OF SCOPE** |
| Per-entity noindex flag mới (thuộc tính CMS/category meta_robots) | **DEFER** — cần data patch + UI, vượt baseline |

## 2. Feature Overview

- **Module**: `Secomm_AiDiscoverability` (`app/code/Secomm/AiDiscoverability`)
- **Feature type**: New · **Priority**: P2
- **User stories**:
  - US-001: As an AI agent, tôi muốn đọc `/llms.txt` để hiểu brand, cấu trúc catalog và các URL quan trọng của store hiện tại.
  - US-002: As a merchant, tôi muốn bật/tắt và curate nội dung llms.txt per store view, kèm preview trùng khớp output thực.
  - US-003: As a developer, tôi muốn endpoint cacheable, session-less, không ảnh hưởng storefront/checkout.

## 3. Acceptance Criteria

- AC-001: `GET /llms.txt` trả 200 `text/plain; charset=utf-8` khi enabled cho store của request; nội dung deterministic (cùng store + dữ liệu → byte-identical).
- AC-002: Khi disabled (global hoặc store), trả **404** (không phải 200 rỗng).
- AC-003: Hai store views (vi_VN default + en_US) cho 2 output khác nhau, mỗi output chỉ chứa canonical URL của store đó (base URL + request path của store).
- AC-004: CMS page `is_active=0` hoặc không gán store hiện tại → không xuất hiện.
- AC-005: Category `is_active=0` hoặc không thuộc store tree → không xuất hiện.
- AC-006: URL có rewrite `redirect_type != 0` → chỉ emit request_path canonical (`is_autogenerated=1`), không emit redirect source.
- AC-007: Duplicate canonical URL (do CMS identifier trùng category path hoặc config trùng) → dedupe, xuất hiện 1 lần.
- AC-008: Không URL nào chứa query string, không có route cart/checkout/account/wishlist/compare/search/customer/admin/internal.
- AC-009: Response không set cookie (không `Set-Cookie`), không khởi tạo customer session (so sánh session files / header trước-sau).
- AC-010: Request thứ 2 trong cùng store hit cache (xác nhận qua cache load hoặc debug log; query count request 2 ≈ 0).
- AC-011: Sau khi save CMS page / category / relevant config section, tag cache bị clean → request sau regen nội dung mới.
- AC-012: Admin preview (chọn store view) output **byte-identical** với public endpoint của store đó (khác nhau duy nhất khi dữ liệu đổi giữa 2 lần gọi).
- AC-013: Storefront HTML `<head>` chứa `<link rel="describedby" href="{storeBaseUrl}/llms.txt">` khi enabled; không có khi disabled.
- AC-014: robots.txt của site giữ nguyên mọi directive hiện có (diff trống khi LC-30 disabled; khi enabled chỉ append, không remove).
- AC-015: Bounded output: tổng số URL ≤ giới hạn config (default 100); mỗi source có limit riêng.
- AC-016: Checkout flow (OSC) và storefront pages không có regression (no new JS/layout impact ngoài 1 link tag).
- AC-017: Mirasvit noindex pattern match một URL được chọn → URL bị loại (khi SHOULD item implement).

## 4. Technical Design

### 4.1 Runtime flow

```
Frontend Router (route 'llms', action path 'llms.txt' via custom Router giống Magento_Robots)
→ Controller\Index\Index (HttpGetActionInterface, session-less, validate enabled → else 404)
→ LlmsTxtProvider::get(int $storeId): string   (cache boundary)
    → CacheInterface::load(SEOCMM_LLMS_STORE_{id}) hit? return
    → LlmsTxtGenerator::generate(int $storeId): string
        → SourceProvider composite (PriorityUrls / CmsPages / Categories / SitemapRefs)
            → mỗi source: eligibility filter (active, store, blocklist) + canonical resolve (UrlFinderInterface / baseUrl)
        → UrlCollector: dedupe by normalized URL, sort deterministic (source order rồi alphabetical)
        → LlmsTxtFormatter (markdown-ish llms.txt format, per llmstxt.org)
    → cache save với tag seocomm_llms (lifetime config, default 86400)
→ Response: text/plain, Cache-Control: public, max-age (config), X-Robots-Tag không cần
```

- Custom frontend Router (match `llms.txt`, sortOrder trước standard) — mirrors `Magento\Robots\Controller\Router` pattern; tránh bị URL-suffix/rewrite can thiệp.
- **HEAD**: controller respond cho GET/HEAD (HttpGetActionInterface); HEAD trả headers không body (Magento tự xử).

### 4.2 Thành phần (files mới — KHÔNG tạo trong task này)

```
app/code/Secomm/AiDiscoverability/
├── registration.php
├── etc/module.xml (sequence: Magento_Store, Magento_Cms, Magento_Catalog, Magento_UrlRewrite, Magento_Sitemap, Magento_Robots)
├── etc/config.xml (defaults: enabled=0, ttl=86400, max_urls=100, limits per source)
├── etc/di.xml
├── etc/frontend/di.xml (router registration + plugin Mirasvit-noindex awareness nếu cần)
├── etc/frontend/routes.xml (route id 'llms')
├── etc/frontend/events.xml (invalidation observers)
├── etc/adminhtml/system.xml (section secomm_ai_discoverability, scope STORE_VIEW)
├── etc/adminhtml/acl.xml
├── etc/frontend/layout/default.xml + view/frontend/templates/describedby.phtml (head link; ifConfig)
├── Controller/Router.php
├── Controller/Index/Index.php
├── Controller/Adminhtml/Preview/Index.php
├── Model/Config.php (ScopeConfigInterface wrapper, STORE_VIEW scope)
├── Service/
│   ├── LlmsTxtProviderInterface.php / LlmsTxtProvider.php      (cache)
│   ├── LlmsTxtGenerator.php                                    (orchestrator)
│   ├── UrlCollector.php                                        (dedupe/sort/bound)
│   ├── LlmsTxtFormatter.php                                    (output format)
│   ├── Eligibility/EligibilityCheckerInterface.php + RouteBlocklistChecker.php + MirasvitNoindexChecker.php
│   └── Source/
│       ├── SourceProviderInterface.php (getUrls(storeId): array)
│       ├── PriorityUrlsSource.php      (config textarea, verified canonical)
│       ├── CmsPagesSource.php          (config-selected page IDs)
│       ├── CategoriesSource.php        (config-selected category IDs)
│       └── SitemapRefsSource.php       (SitemapManager refs)
├── Observer/InvalidateCache.php       (generic tag-clean cho các event đã list)
├── Plugin/RobotsAppendPlugin.php      (SHOULD — after on Magento\Robots\Model\Robots)
├── i18n/vi_VN.csv, i18n/en_US.csv
├── README.md, CHANGELOG.md           ([WARN] rule cho Secomm module)
└── Test/Unit (Generator, UrlCollector, Eligibility) + Test/Integration (controller, cache, store-scoping)
```

### 4.3 Config fields (scope STORE_VIEW)

| Path | Type | Default |
|---|---|---|
| `seocomm_ai_discoverability/general/enabled` | select | 0 |
| `seocomm_ai_discoverability/general/brand_summary` | textarea | (store name fallback) |
| `seocomm_ai_discoverability/general/priority_urls` | textarea (internal paths, 1/dòng) | — |
| `seocomm_ai_discoverability/urls/cms_pages` | multiselect (active pages của store) | — |
| `seocomm_ai_discoverability/urls/categories` | multiselect (active categories của store) | — |
| `seocomm_ai_discoverability/urls/include_sitemap_refs` | select | 1 |
| `seocomm_ai_discoverability/urls/blocked_patterns` | textarea (regex/prefix) | — |
| `seocomm_ai_discoverability/cache/lifetime` | text (s) | 86400 |
| `seocomm_ai_discoverability/cache/max_urls` | text | 100 |

### 4.4 Cache & invalidation

- Key: `SEOCMM_LLMS_STORE_{storeId}`; tag: `seocomm_llms`. Lazy regen, TTL purgeable.
- Observers (mỗi cái chỉ clean tag — nhẹ): `admin_system_config_changed_section_seocomm_ai_discoverability`, `cms_page_save_commit_after`, `cms_page_delete_commit_after`, `catalog_category_save_commit_after`, `catalog_category_delete_commit_after`, `clean_sitemap`.
- Không cron.

### 4.5 Security / privacy

- Chỉ emit URL public; không customer data; không log nội dung; controller không nhận parameter nào (store do URL resolution quyết định như mọi frontend request).
- Preview admin: ACL `Secomm_AiDiscoverability::preview`, chỉ render text trong admin context, không write cache public.
- Blocklist mặc định: `checkout, cart, customer, account, wishlist, catalogsearch, product_compare, review/customer*, admin, api`, mọi path chứa `?`/`&`.

### 4.6 Output format (llms.txt v0.x chuẩn)

```
# {Store name}
> {brand_summary}

## Priority
- [{title}]: {canonicalUrl}

## Pages
- ...

## Collections
- ...

## Sitemap
- [{sitemapUrl}]
```

## 5. Tests (tóm tắt — chi tiết trong AC)

Unit: UrlCollector (dedupe/sort/bound), EligibilityChecker, Formatter determinism, Config scope fallback. Integration: mọi AC-001→017 (2 stores, disabled→404, no session/no Set-Cookie header assert, cache hit assert bằng mocked CacheInterface, observers clean tag, admin preview === runtime, layout link presence/absence, robots.txt diff trống khi disabled). QC manual: storefront + OSC smoke trên cả 2 store views.

## 6. Risks / Assumptions / Unresolved

- **Giả định**: llms.txt spec (llmstxt.org draft) ổn định đủ cho baseline; format có thể đổi nhẹ — Formatter tách riêng để thay rẻ.
- **Rủi ro**: Mirasvit upgrade đổi `seo/general/*` paths (chỉ ảnh hưởng SHOULD item — làm mềm bằng try/catch config absent). Varnish/FPC prod có thể cache 404/200 khác với dev — cần kiểm tra sau khi deploy (production infra TBD theo project context).
- **Unresolved (không chặn)**: brand summary content thực tế là nội dung merchant — để placeholder + admin config.
- **Không cần DB table** (curated config là đủ; không có persistence need được chứng minh).
- **Không cần hỏi Owner** — mọi quyết định còn lại đều suy ra được từ repository evidence như trên.

## 7. Escalation

- Spec này chờ **TL review** (Mode A gate) trước khi lập implementation plan + code.
- Không chạm area high-risk §12 (không payment/checkout/order/PII/DB migration).
