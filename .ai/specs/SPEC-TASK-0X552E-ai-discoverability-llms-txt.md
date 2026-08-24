# [SLP][TASK-0X552E] LC-30 — AI Discoverability / AIEO Baseline (`/llms.txt`)

Specification ID: SPEC-TASK-0X552E

> **External ref**: LC-30 · **Mode**: A (spec-first) · **Status**: DRAFT v2 (đã hiệu chỉnh canonical/noindex/robots/routing theo audit Mirasvit sâu) — chờ TL review. **Không có production code.**
> **Scope guard**: curated discovery, KHÔNG phải sitemap thứ hai; OUT: llms-full.txt, markdown per-page, MCP/UCP/ACP, chatbot, agent cart/checkout, public commerce API, embeddings/vector DB, AI-generated content, Schema.org engine, Cloudflare.

---

## 0. Repository Reality Audit (A–H) — v2, có bằng chứng Mirasvit sâu

### A. Canonical / SEO

- **SEO stack thật**: Mirasvit SEO suite (v2.12.8) đầy đủ bật trong `app/etc/config.php` (`Seo`, `SeoMarkup`, `SeoSitemap`, `SeoToolbar`, `SeoAi`, `SeoAudit`, `SeoAutolink`, `SeoContent`, `SeoFilter`, `SeoNavigation` + compat `Hyva_MirasvitSeo*`). Không có LC-21, không SEO code Secomm, không llms.txt sẵn có. `Mirasvit_SeoAi` = AI meta auto-fix, không liên quan.
- **Canonical do Mirasvit sinh** tại [Mirasvit/Seo/Observer/Canonical.php](app/code/Mirasvit/Seo/Observer/Canonical.php), thứ tự ưu tiên:
  1. **CanonicalRewrite rules** (`Mirasvit\Seo\Service\CanonicalRewrite\CanonicalRewriteService`) — regex match `urlInterface->getCurrentUrl()` + conditions theo `registry('current_product'/'current_category')` → override tất cả.
  2. Product: URL / custom `m_seo_canonical` (chỉ **product** có attribute này — `DataPatch108`; **không có attribute tương đương cho category/CMS**).
  3. **Category** (line 246+): `$category = registry('current_category')` → ép `request_path` từ bảng `url_rewrite` (`entity_type='category' AND redirect_type=0 AND store_id=? AND entity_id=?` ORDER BY `url_rewrite_id` ASC, lấy **dòng đầu** — "oldest rewrite wins", có thể khác `getUrl()` mặc định) → `CanonicalLayeredService::getCanonicalUrl()` (không filter áp dụng ⇒ plain `$category->getUrl()`, query stripped).
  4. **CMS**: không có branch riêng → `else { getRequestUri() }` = **self-canonical theo request path hiện tại** (`Helper\Data::getBaseUri()` đọc `$_SERVER['REQUEST_URI']`), query stripped.
  5. Post-processing: bỏ store-code khỏi path, cross-domain/https, pagination `?p=N`, **trailing slash theo `Config::getTrailingSlash()`** (append/strip, skip `.html`), double-slash cleanup.
- **Kết luận then chốt**: Mirasvit canonical **KHÔNG thể resolve ngoài HTTP request** (phụ thuộc `current_category` registry, `$_SERVER['REQUEST_URI']`, `getCurrentUrl()`, Layer state). Không tồn tại API "resolve canonical cho entity X". → LC-30 **không tái sử dụng** observer/service này, và **không gọi `UrlFinderInterface` là chân lý tuyệt đối**; thay vào đó định nghĩa *bounded canonical policy* tái lập được phần chứng minh được (mục 4.2).

### B. Robots.txt

- Core `Magento_Robots` (route `robots`, `Controller/Router.php` sortOrder 10, custom instructions từ `design/search_engine_robots/custom_instructions`). Mirasvit **không có plugin nào trên `Magento\Robots`**; duy nhất `SeoSitemap/Model/Config/Backend/Robots.php` extends core backend để sửa `$_cacheTag` — kế thừa hành vi append Sitemap line của core.
- **Quyết định**: `robots.txt code modification = NOT REQUIRED for baseline`. Lý do: (a) `/llms.txt` là public frontend route — robots.txt mặc định không chặn nó, và việc expose bởi robots.txt không phải cơ chế discovery chuẩn cho llms.txt; (b) discovery signal của LC-30 là `rel="describedby"`; (c) thêm comment không có ý nghĩa crawler-policy. Hợp đồng: robots.txt = crawler access policy (giữ nguyên), llms.txt = discovery guidance, LC-30 không thêm/được/bớt directive nào. Nếu merchant sau này muốn chặn AI crawler → dùng `custom_instructions` có sẵn (ngoài scope).

### C. Sitemap

- Active: `Mirasvit_SeoSitemap` (preference lên `Magento\Sitemap\Model\Sitemap`); generation qua cron core `sitemap_generate` (`vendor/magento/module-sitemap/etc/crontab.xml` → `Magento\Sitemap\Model\Observer::scheduledGenerateSitemaps`), file per store trên filesystem, tham chiếu bằng `Magento\Sitemap\Model\SitemapManager`. LC-30 **chỉ tham chiếu** (đọc rows sitemap qua `SitemapManager`, build public URL = `{storeBaseUrl}/{path}{filename}`), không generate/duplicate. Core dispatch event `clean_sitemap` sau mỗi lần generate.

### D. CMS / Category Indexability

- CMS: `cms_page.is_active` + `cms_page_store` (store 0 = all; resolve `GetPageByIdentifier`). **Không có `meta_robots`** trong core 2.4.8.
- Category: `is_active` EAV; category form chỉ có meta_title/keywords/description — **không có meta_robots**. Mirasvit cũng không thêm attribute meta_robots cho category/CMS.
- **Nguồn noindex thật của site** = Mirasvit config `seo/general/noindex_pages2` (chi tiết mục 4.3) — đây là chính sách SEO hiệu dụng duy nhất, LC-30 tái sử dụng (xem H).

### E. URL Rewrite / Public URL Eligibility

- `Magento\UrlRewrite\Model\UrlFinderInterface`; canonical rewrite = `redirect_type = 0`. **Lưu ý quan trọng (khác v1)**: Mirasvit category canonical dùng **dòng `url_rewrite` đầu tiên theo `url_rewrite_id` ASC** với điều kiện `entity_type/category, redirect_type=0, store_id, entity_id` — không lọc `is_autogenerated`. LC-30 dùng **đúng query này** để khớp hành vi category của storefront (proven). Redirect rewrites (`redirect_type != 0`) bị loại.
- Query/filter/sort variants: chỉ emit bare canonical path, không query string — loại theo cấu trúc.
- Cross-store: mọi lookup ràng buộc `store_id`; base URL từ `StoreManagerInterface`. CMS canonical URL = `{storeBaseUrl}/{identifier}` (+ trailing slash policy).
- Pattern usage tương đương trong repo: `Mirasvit\SeoAudit\Service\Resolver\CatalogUrlRewriteResolver`.

### F. Cache / Invalidation

- Pattern Secomm sẵn có: `Secomm/Ghtk/Model/RateCache.php` (`CacheInterface` inject), `Secomm/AddressDropdown/CustomerData/CityData.php` (`CacheFrontendPool` default + `TypeListInterface`). Không Secomm module nào có `cache.xml`.
- LC-30: `CacheInterface` frontend `default`, ID/tag per store, tag-clean observers, **lazy regeneration, KHÔNG cron** (nguồn curated nhỏ). Chi tiết hợp đồng cache ở 4.4.

### G. Admin Config / Preview

- Pattern: `Secomm/Base/etc/adminhtml/system.xml` (tab `secomm` sortOrder 5000) + `Model/Config.php` wrapper. Admin controllers chuẩn `Magento\Backend\App\Action` + ACL (ví dụ đầy đủ nhất: `Secomm/GhnAddressMapper/Controller/Adminhtml/Mapping/*`). Chưa có preview controller nào — LC-30 là cái đầu.

### H. Module Collision / Dependencies

| Khía cạnh | Tái sử dụng | Collision/Duplication risk |
|---|---|---|
| Mirasvit_Seo canonical | **Không** (request-bound, đã chứng minh) — LC-30 tự bounded policy tái lập phần proven | Thấp; LC-30 không render page |
| Mirasvit_Seo noindex (`noindex_pages2` + `Helper\Data::checkPattern`) | **Có** — qua adapter mềm (4.3) | Thấp — chỉ đọc config + gọi pure function |
| Mirasvit_SeoSitemap | tham chiếu qua `SitemapManager` | Thấp |
| Magento_Robots | không chạm | Không |
| Magento_Cms/Catalog/UrlRewrite | chuẩn | Thấp |
| Hyva theme | `<link rel="describedby">` qua layout `default.xml` của module | Thấp |
| Checkout/OSC | không chạm | Không |

**Module dependency**: `module.xml` sequence chỉ các module Magento core (`Magento_Store`, `Magento_Cms`, `Magento_Catalog`, `Magento_UrlRewrite`, `Magento_Sitemap`). **Mirasvit là integration mềm** — adapter kiểm tra `ModuleManager::isOutputEnabled('Mirasvit_Seo')` runtime; module LC-30 hoạt động đầy đủ (trừ noindex-rule awareness) khi Mirasvit absent. Không phụ thuộc cứng.

---

## 1. Scope Decision (final)

| Item | Quyết định |
|---|---|
| `/llms.txt` root-path endpoint, store-view aware, curated | **MUST** |
| Eligibility: enabled, public, store-applicable, non-redirect, no query, route blocklist (cart/checkout/account/wishlist/compare/search/admin/api) | **MUST** |
| Bounded canonical policy per 4.2 (kể cả documented exceptions) | **MUST** |
| Noindex: tái sử dụng Mirasvit `noindex_pages2` patterns qua adapter (loại URL match rule noindex) | **MUST** (adapter mềm, fail-open về "giữ URL" nếu Mirasvit off? — không: **fail-closed = bỏ URL khỏi llms.txt chỉ khi rule khớp**; Mirasvit off ⇒ không có rule nào ⇒ mọi curated URL giữ lại — đúng ngữ nghĩa) |
| `rel="describedby"` `<link>` trong `<head>` (layout `default.xml` + `ifConfig`) | **MUST** |
| Admin config (STORE_VIEW scope) + preview cùng generator | **MUST** |
| Cache per store + targeted tag invalidation + lazy regen | **MUST** |
| Disabled → 404 (global hoặc store) | **MUST** |
| HTTP `Link` header trên storefront responses | **DEFER** (link tag đủ baseline; cần kiểm chứng tương tác FPC/Varnish trên infra thật) |
| Skip URL nếu match CanonicalRewrite rule không-điều-kiện (đánh dấu ngoại lệ canonical) | **SHOULD** (rẻ: `preg_match` pure với `reg_expression` của rule no-condition) |
| Per-entity meta_robots attribute mới cho CMS/category | **DEFER** |
| Merchant blacklist patterns riêng của LC-30 | **DEFER** — không cần: nguồn đã curated + đã tái dùng noindex policy thật của site |
| robots.txt modification | **NOT REQUIRED** (mục 0.B) |
| Cron regeneration | **OUT** |
| llms-full.txt, markdown per-page, MCP/UCP/ACP, chatbot, commerce API, embeddings, AI content, Schema engine, Cloudflare | **OUT** |

---

## 2. Feature Overview

- **Module**: `Secomm_AiDiscoverability` · **Type**: New · **Priority**: P2
- **Invariant chính**: *An emitted URL must match the effective public canonical URL policy of the storefront for that store view* — trong phạm vi bounded policy 4.2; mọi trường hợp ngoài phạm vi được loại bỏ hoặc ghi nhận là exception (không giả vờ tuyệt đối).

## 3. Canonical & Indexability Contract (trung tâm của bản hiệu chỉnh)

### 3.1 Canonical policy của LC-30 (bounded, proven)

Với mỗi URL emit, LC-30 tính:

1. **Path**:
   - Category: `url_rewrite` row đầu tiên (`url_rewrite_id` ASC) với `entity_type='category' AND entity_id=? AND store_id=? AND redirect_type=0` — **đúng thuật toán Mirasvit** (`Canonical::getCategoryRewrite()`, line 430-440). Không có row → skip entity (không public).
   - CMS page: `identifier` (canonical CMS = self-canonical request path — proven ở 0.A mục 4).
   - Priority URL (merchant nhập internal path): dùng path đã chuẩn hóa (strip query, dẫn `/`), resolve qua store base URL; **chỉ chấp nhận path nội bộ cùng domain** (mục 6).
   - Home page: chính store base URL (không path).
2. **Base URL**: `StoreManagerInterface::getStore($storeId)->getBaseUrl()` (web/secure theo cấu hình store).
3. **Post-processing tái lập Mirasvit (pure, offline-able)**:
   - strip query string (`strtok($url, '?')`);
   - trailing slash theo `Mirasvit\Seo\Model\Config::getTrailingSlash()` qua adapter (skip đuôi `.html/.htm`) — khi Mirasvit off: giữ nguyên path nguyên bản;
   - collapse double slash;
   - bỏ store-code prefix khỏi path nếu store code appended in URL (đối chiếu `Config` của Mirasvit; core: store code luôn hiện khi `URL_TYPE_STORE` — dùng UrlResolver chuẩn của store).

### 3.2 Exceptions đã biết (documented, không che giấu)

- **CanonicalRewrite rules có điều kiện** (product/category condition) cần registry → không đánh giá offline được ⇒ LC-30 không nhìn thấy. Mitigation: (a) SHOULD skip URL nếu khớp rule **không điều kiện** (regex pure); (b) spec ghi nhận: nếu merchant cấu hình canonical_rewrite có điều kiện trỏ category/CMS curated sang URL khác, llms.txt có thể lệch canonical render — merchant chịu trách nhiệm không curate các entity đó (ghi chú trong admin field comment + README).
- **Cross-domain canonical** (`getCrossDomainStore`): áp cho product (`seo_canonical_store_id`) và config cross-domain — LC-30 không emit product URL ở baseline ⇒ không ảnh hưởng; ghi nhận trong README.
- **Pagination/layered**: LC-30 chỉ emit bare URL không filter/`?p=` ⇒ mọi chính sách layered/pagination của Mirasvit quy về "no filters applied" = plain URL — khớp proven behavior (`CANONICAL_LAYERED_NOFILTERS` default branch).

### 3.3 Indexability policy

- Nguồn thật duy nhất trên site: `seo/general/noindex_pages2` (JSON/serialized array các row `{pattern (wildcard `*`, case-insensitive), option (int robots code)}`; `Mirasvit\Seo\Model\Config::getNoindexPages(?int $store)` merge store+default; `Mirasvit\Seo\Helper\Data::checkPattern($value, $pattern)` là **pure string function** — đã chứng minh eval được offline).
- LC-30 adapter `MirasvitNoindexPolicy`: với mỗi URL path, duyệt rules của store; nếu `checkPattern(path, pattern)` khớp và `option` maps to NOINDEX (`getMetaRobotsByCode` 1 hoặc 2) → **loại URL**. Match theo cả path-only (bỏ base URL) — tương thích cách plugin runtime match `getBaseUri()`.
- `https_noindex_pages` là chính sách toàn store (không per-URL) → ngoài scope per-URL; không áp.
- Không tạo attribute meta_robots mới; không tạo blacklist riêng trừ khi TL yêu cầu sau QC.

---

## 4. Technical Design (final)

### 4.1 Routing — root `/llms.txt`

- **Seam**: custom frontend router copy pattern `Magento\Robots\Controller\Router` — class `Secomm\AiDiscoverability\Controller\Router` implements `RouterInterface`, đăng ký qua `etc/frontend/di.xml` vào virtual type `RouterList` (sortOrder **20**, sau robots 10) — match chính xác path `llms.txt` (đối chiếu request path sau khi strip base path) → set request module/route/controller/action nội bộ. KHÔNG dùng frontName route `/aidiscoverability/...`; không touch CMS/catalog routing (match false ⇒ return null cho router kế tiếp).
- **Controller**: `Controller\Index\Index` implements `HttpGetActionInterface` + `HttpMethodInterface` — chỉ xử lý GET/HEAD; các method khác → 404/405 theo hành vi Magento (`Action\AbstractAction` novelty: xác nhận HEAD trả cùng status/headers với body rỗng — Magento HTTP kernel tự strip body cho HEAD; nếu không, controller tự trả empty body).
- URL cuối: `{storeBaseUrl}/llms.txt` (đúng văn bản task; với store code in URL sẽ là `{baseUrl}/{storeCode}/llms.txt` — nhất quán với mọi frontend path của store đó).

### 4.2 Runtime flow

```
Router (match 'llms.txt', sortOrder 20)
→ Controller\Index\Index (HttpGetActionInterface, session-less)
   enabled? (STORE_VIEW scope, fallback website/default) — không → 404 result
→ LlmsTxtProvider::get(int $storeId): string        [cache boundary]
   cache hit (ID seocomm_llms_txt_store_{storeId}) → return, KHÔNG đụng DB
   miss → LlmsTxtGenerator::generate(int $storeId)
      → Config (brand summary, priority paths, CMS IDs, category IDs, limits)
      → Sources: PriorityUrls / CmsPages / Categories / SitemapRefs
          mỗi URL → CanonicalPolicy (3.1) → Eligibility (active/store/blocklist/noindex 3.3)
      → UrlCollector: normalize → dedupe (so khớp URL full sau normalize) → sort deterministic → bound per-source & total
      → LlmsTxtFormatter (mục 6)
   → cache save (tag seocomm_llms, TTL config)
→ Response: 200, text/plain; charset=UTF-8, Cache-Control public max-age (default 3600),
   ETag = sha1(body) (hỗ trợ HEAD/revalidation)
```

- Admin Preview: `Controller\Adminhtml\Preview\Index` (ACL `Secomm_AiDiscoverability::preview`, param `store`) gọi **cùng** `LlmsTxtGenerator::generate()` (không qua `LlmsTxtProvider` cache public). Equivalence: same store + same effective config + same source state ⇒ **same generated body bytes**.

### 4.3 Config fields (scope STORE_VIEW, section `seocomm_ai_discoverability`)

| Path | Type | Default |
|---|---|---|
| `general/enabled` | select | 0 |
| `general/brand_summary` | textarea | fallback: store name |
| `general/priority_paths` | textarea, internal paths 1/dòng | — |
| `urls/cms_pages` | multiselect (active pages của store) | — |
| `urls/categories` | multiselect (active categories của store) | — |
| `urls/include_sitemap_refs` | select | 1 |
| `cache/lifetime` | text (giây) | 86400 |
| `cache/max_urls` | text | 100 |

(Nhóm cũ `urls/blocked_patterns` đã **xóa** theo scope decision.)

### 4.4 Cache & invalidation contract

- **Cache ID**: `seocomm_llms_txt_store_{storeId}` (đã sửa lỗi chính tả `SEOCMM` của v1; dùng lowercase ID thống nhất với tag). Store ID là boundary duy nhất — không key theo website.
- **Tag**: `seocomm_llms` (mọi entries) + `seocomm_llms_store_{storeId}` (per store).
- **Lifetime**: config `cache/lifetime` (default 86400); lazy regen khi miss/expire — không pre-generate, không cron.
- **Invalidation — theo phạm vi ảnh hưởng, không clean global khi có thể hẹp**:

| Event | Phạm vi clean |
|---|---|
| `admin_system_config_changed_section_seocomm_ai_discoverability` | theo scope của changed path: store → 1 store; website/default → mọi store kế thừa scope đó (tính từ `StoreManagerInterface`) |
| `cms_page_save_commit_after` / `cms_page_delete_commit_after` | các store của page (`cms_page_store`; store 0 ⇒ mọi store) |
| `catalog_category_save_commit_after` / `catalog_category_delete_commit_after` | store_ids của category trong store tree (category store assignment) |
| `admin_system_config_changed_section_seo` (Mirasvit noindex/trailing-slash đổi) | mọi store (config `seo/*` scope không track per-store trong event payload) |
| `clean_sitemap` (core `Magento\Sitemap\Model\Observer` dispatch sau mỗi generate — proven) | mọi store (sitemap refs ít, regen rẻ) |

- Observer chỉ làm `CacheInterface::clean(CLEANING_MODE_MATCHING_TAG, [tag])` — không rebuild trong observer.

### 4.5 Security / privacy

- Chỉ emit public URL curated; không customer data; không log body; controller không nhận request param.
- Route blocklist mặc định (prefix path): `checkout`, `cart`, `customer`, `account`, `wishlist`, `catalogsearch`, `product_compare`, `review`, `admin`, `api`, `rest`, `graphql` — cố định trong code (không config — tránh trở thành blacklist trùng lặp mục 3.3); mọi path chứa `?`/`&` bị loại.
- External URLs trong `priority_paths`: **fail-closed** — chỉ chấp nhận internal path bắt đầu `/` cùng domain store; URL tuyệt đối hay domain khác bị loại im lặng + đếm trong admin preview note ("N URL bị loại: lý do").

---

## 5. Output format (deterministic)

```
# {Store name}                       ← H1, store name của store view
> {brand_summary}                    ← 1 dòng blockquote, sanitized (mục dưới)

Locale: {store locale code}          ← vd vi_VN / en_US

## Priority Pages
- [{label}]: {canonicalUrl}

## Collections
- [{category name}]: {canonicalUrl}

## Pages
- [{cms page title}]: {canonicalUrl}

## Sitemap
- [Sitemap]: {sitemapUrl}
```

Quy tắc:
- **Ordering**: section theo thứ tự cố định trên; trong section: thứ tự config (priority), rồi tên entity sort theo `mb_strtolower` (CMS, categories) — deterministic toàn bộ.
- **Dedup**: theo full canonical URL sau normalize; giữ lần xuất hiện đầu theo thứ tự section.
- **Bounds**: per-source limit config (cms ≤ 20, categories ≤ 20 — hard default trong code, đủ curated), tổng ≤ `cache/max_urls` (default 100); vượt → cắt theo thứ tự, ghi log `notice` một dòng.
- **UTF-8**: output luôn UTF-8; `Content-Type: text/plain; charset=UTF-8`; không BOM; newline `\n`.
- **Sanitize merchant input** (brand_summary, labels): strip control chars; escape `[` `]` trong label bằng `\[` `\]` để không phá cú pháp link; giới hạn 1 dòng cho summary (cắt ở 500 chars); URL escape theo `htmlspecialchars`-safe output (text/plain nên chỉ cần strip whitespace/control).
- **Malformed priority paths** (không bắt đầu `/`, chứa query, trùng blocklist, không resolve được) → loại im lặng + note trong preview.
- **External-domain URLs**: không bao giờ emit (fail-closed, 4.5).

## 6. HTTP / session contract

| Trường hợp | Hành vi |
|---|---|
| GET, enabled | 200, body text/plain UTF-8 |
| GET, disabled (store/global) | **404**, body rỗng |
| HEAD | cùng status + headers, body rỗng |
| POST/PUT/DELETE... | 404/405 (không generate) |
| Redirect | KHÔNG bao giờ redirect từ `/llms.txt` sang route khác |
| Session | không init customer session, không PHP session start, không `Set-Cookie` từ endpoint này |
| Cache headers | `Cache-Control: public, max-age=3600` + `ETag: sha1(body)` |
| Cache hit proof | qua **provider seam**: integration test assert `LlmsTxtProvider` return từ `CacheInterface::load` (mock/spy), KHÔNG dùng "query count ≈ 0" |

## 7. File-level implementation plan (chưa tạo — chỉ danh sách)

```
app/code/Secomm/AiDiscoverability/
├── registration.php
├── etc/module.xml                       (sequence: Magento_Store, Magento_Cms, Magento_Catalog, Magento_UrlRewrite, Magento_Sitemap — KHÔNG sequence Mirasvit)
├── etc/config.xml
├── etc/di.xml                            (adapter wiring, ModuleManager check cho MirasvitNoindexPolicy)
├── etc/frontend/di.xml                   (RouterList entry sortOrder 20)
├── etc/frontend/events.xml               (invalidation observers)
├── etc/adminhtml/system.xml, acl.xml
├── etc/frontend/layout/default.html? → default.xml + templates/head/describedby.phtml   (ifConfig)
├── Controller/Router.php
├── Controller/Index/Index.php
├── Controller/Adminhtml/Preview/Index.php
├── Model/Config.php
├── Service/
│   ├── LlmsTxtProviderInterface.php / LlmsTxtProvider.php
│   ├── LlmsTxtGenerator.php
│   ├── Canonical/CanonicalPolicyInterface.php / StorefrontCanonicalPolicy.php   (3.1)
│   ├── Canonical/TrailingSlashRuleInterface.php (adapter seam)
│   ├── Indexability/IndexabilityPolicyInterface.php / NullIndexabilityPolicy.php
│   ├── Indexability/Mirasvit/NoindexPatternPolicy.php + TrailingSlashRule.php   (soft adapter, check ModuleManager)
│   ├── Eligibility/EligibilityChecker.php (active/store/route blocklist/query)
│   ├── Source/{SourceProviderInterface, PriorityUrlsSource, CmsPagesSource, CategoriesSource, SitemapRefsSource}.php
│   ├── UrlCollector.php
│   └── LlmsTxtFormatter.php
├── Observer/{ConfigInvalidation, CmsInvalidation, CategoryInvalidation, SeoConfigInvalidation, SitemapInvalidation}.php
├── i18n/vi_VN.csv, en_US.csv
├── README.md, CHANGELOG.md
└── Test/Unit/... + Test/Integration/...
```

## 8. Acceptance Criteria

- AC-001: `GET /llms.txt` (enabled, store vi_VN) → 200 `text/plain; charset=UTF-8`, đúng cấu trúc mục 5, deterministic (2 lần generate liên tiếp → byte-identical).
- AC-002: disabled → 404 body rỗng; bật lại → 200.
- AC-003: store en_US (store code in URL) → endpoint `{base}/{code}/llms.txt` hoạt động; output chỉ chứa URL của store en_US (base URL + rewrites store đó); khác output vi_VN.
- AC-004: CMS `is_active=0` hoặc không gán store → không xuất hiện; bật lại + save → sau invalidation, xuất hiện.
- AC-005: Category `is_active=0` → không xuất hiện.
- AC-006: Category có nhiều rewrite `redirect_type=0` → emit theo dòng `url_rewrite_id` nhỏ nhất (khớp Mirasvit); rewrite `redirect_type != 0` không bao giờ được emit.
- AC-007: Hai curated nguồn cho cùng URL (priority path trùng CMS identifier) → dedupe, xuất 1 lần ở section đầu.
- AC-008: Không URL chứa query string; không URL thuộc blocklist route (thử thêm `checkout/cart` vào priority_paths → bị loại, ghi note preview).
- AC-009: Noindex: thêm rule `noindex_pages2` pattern khớp một curated CMS path với option NOINDEX → URL biến mất khỏi llms.txt (sau invalidation `admin_system_config_changed_section_seo`).
- AC-010: Trailing slash: đặt Mirasvit trailing-slash policy → URL emit khớp policy (có/không `/` cuối).
- AC-011: Mirasvit_Seo disable (integration test) → endpoint vẫn 200; mọi curated URL còn lại (noindex rules không áp), trailing slash giữ nguyên.
- AC-012: Response không có `Set-Cookie`; session file/customer session không khởi tạo (assert headers + session state qua test harness).
- AC-013: Cache hit chứng minh qua provider seam: request 2 trong cùng store dùng giá trị `CacheInterface::load` (spy), generator không chạy lại.
- AC-014: Invalidation hẹp: save CMS page thuộc store A → chỉ cache store A clean (store B cache còn nguyên — assert tag per store).
- AC-015: Admin preview store vi_VN ≡ public `/llms.txt` vi_VN (so sánh bytes trong cùng state dữ liệu); preview không ghi cache public.
- AC-016: `<head>` storefront chứa `<link rel="describedby" href=".../llms.txt">` khi enabled; không có khi disabled (ifConfig).
- AC-017: robots.txt diff trước/sau khi bật module = rỗng (không code path nào của LC-30 sửa robots).
- AC-018: Bounded: curate 150 URL với max 100 → output đúng 100, log notice 1 dòng.
- AC-019: POST `/llms.txt` → không 200-with-body (404/405), không generate, không cache write.
- AC-020: Checkout (OSC) + storefront smoke trên 2 store views: không regression (chỉ thêm 1 link tag head).

## 9. Test matrix (tóm tắt)

- **Unit**: `StorefrontCanonicalPolicy` (rewrite-first-row, query strip, trailing slash, store-code, html-suffix); `UrlCollector` (dedupe/sort/bound); `LlmsTxtFormatter` (determinism, sanitize label `[`, UTF-8, section order); `EligibilityChecker` (blocklist, query, external); `MirasvitNoindexPolicy` (wildcard match, option 1/2 loại, 3/4 giữ; Mirasvit off → NullPolicy).
- **Integration**: AC-001→020 (fixture 2 store views, store-code-in-URL config); cache spy; event dispatch assert tag clean per store; admin preview action với ACL.
- **QC manual**: 2 store views trên Hyvä storefront + OSC smoke; Varnish/FPC behavior của text/plain endpoint ghi nhận trong release checklist (prod infra TBD).

## 10. Risks / Assumptions / Unresolved

- **Exception canonical** (3.2): CanonicalRewrite rules có điều kiện — không thể evaluate offline; mitigated bằng SHOULD skip rule không-điều-kiện + documentation. Đây là rủi ro được chấp nhận của baseline, không thể loại trừ không bằng chứng thêm.
- **Giả định**: llms.txt spec (llmstxt.org draft) đủ ổn định; Formatter tách riêng để đổi rẻ.
- **Mirasvit upgrade** có thể đổi `seo/general/*` / thuật toán canonical — adapter cô lập; test integration sẽ fail rõ nếu path đổi.
- Store-code-in-URL với base path lồng nhau cần test kỹ (AC-003) — pattern robots.txt của core đã xử lý tương tự.
- Không có DB table (không persistence need). Không cron. Không hỏi Owner điều gì có thể trả lời bằng repo — không còn câu hỏi kỹ thuật mở.

## 11. Escalation

- Spec chờ **TL review** (Level 2 — architecture decision: bounded canonical policy + soft Mirasvit dependency). Không chạm high-risk area §12.

## 12. Follow-up LC-30.1 — llms.txt v2 conformance + store metadata (2026-08-24)

> Bounded delta lên LC-30 đã tích hợp (`22997372`). Không redesign. Scope guard §0 giữ nguyên.

### 12.1 Markdown link format (MUST FIX)

Entry đổi từ `- [Label]: URL` (dạng cũ không phải Markdown) sang dạng Markdown chuẩn của llms.txt v2:

```
- [Label](https://example.com/path)
- [Label](https://example.com/path): Optional description
```

Lý do: `[Label]: URL` không phải cú pháp Markdown hyperlink hợp lệ; các parser Markdown/AI
agent chuẩn mong đợi `[text](url)`. Determinism, sanitization URL, eligibility, section order
giữ nguyên không đổi.

### 12.2 Site / Brand Title (store-view scoped)

Vấn đề: H1 hiện lấy `$store->getName()` → lộ nhãn nội bộ kiểu `# Default Store View`, không
phải brand identity hướng tới AI.

Giải pháp: config mới `seocomm_ai_discoverability/general/site_title` (store-view scoped).
Fallback chain (nhỏ nhất → lớn nhất, không tạo DB table):

1. `seocomm_ai_discoverability/general/site_title` (LC-30 config)
2. `general/store_information/name` (Store Information của Magento — merchant identity công
   khai, store-scoped, có sẵn)
3. `$store->getName()` (last resort — nhãn nội bộ, chỉ khi không có gì tốt hơn)

Brand / Site Summary (acceptance 2026-08-24, second pass): fallback ĐỔI cho chuẩn public-safe —
1. `general/brand_summary` nếu có; 2. nếu rỗng dùng đúng effective Site / Brand Title (chain
12.2); 3. nếu không có public title → **omit blockquote entirely**. `$store->getName()` KHÔNG
BAO GIỜ được emit làm summary (chỉ được dùng làm last-resort H1). Không generate prose.

### 12.3 Optional entry descriptions

Entry có thể mang `description` (optional; thiếu metadata KHÔNG loại URL):
- CMS: dùng `meta_description` của page nếu có
- Category: dùng `meta_description` của category nếu có
- Priority URLs / Sitemap: không description (không phát minh logic crawl/resolve)

Ràng buộc: một dòng; bound ≤ 200 ký tự; strip HTML tag; strip control chars; KHÔNG thực thi
CMS directives (text thuần, không render); không thêm dependency nội dung/session. Không xây
metadata subsystem, không UI chỉnh description từng entry.

Lý do: mô tả sẵn có tăng khả năng hiểu của agent mà không mở rộng Agentic Commerce scope.

### 12.4 Currency metadata

Thêm `Currency: <code>` ngay sau `Locale:`. Nguồn: `currency/options/default` theo store scope
(deterministic theo config — không dùng current-currency theo visitor/session vì sẽ phá
byte-determinism của endpoint). Lý do: tiền tệ là ecommerce context native, rẻ, store-aware.

### 12.5 Không đổi

Canonical policy, Mirasvit soft integration, noindex, sitemap reuse, cache/invalidation,
root router, GET/HEAD contract, rel="describedby", robots.txt — giữ nguyên (chỉ đổi khi có
regression test chứng minh conflict trực tiếp).

### 12.6 Test matrix mở rộng

- Formatter: byte-exact `- [Label](URL)`, `: Description` optional, không colon khi thiếu
  description, sanitization description (HTML/control), determinism, dòng Currency
- Title: configured wins; fallback store_information/name; last-resort store name; isolation
  theo store (unit)
- Currency: store-scoped value, khác nhau theo storeId (unit)
- CMS/Category description: có metadata → emit; rỗng → không colon; không ảnh hưởng eligibility
- Regression: canonical/noindex/max-urls/cache/GET-HEAD/describedby không đổi
