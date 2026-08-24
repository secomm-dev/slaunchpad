# Implementation Plan: LA-22 — AI Commerce Read Layer (Secomm_AiCommerce facade)

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | LA-22 / TASK-QV3R7T |
| Specification | **REQUIRED**: `.ai/specs/SPEC-TASK-QV3R7T-la-22-ai-commerce-read-layer.md` (status: APPROVED FOR IMPLEMENTATION PLANNING; TL decision §7b = Option C NOW) |
| Author | AI Coding (thanhle session) |
| Reviewer (TL) | TL (pending — plan approval trước khi code) |
| Workflow Mode | A |
| Date | 2026-08-24 |

## 1. Approach

Bounded read-only facade module `Secomm_AiCommerce` làm **security boundary** giữa AI agent và Magento. Facade KHÔNG tự cài business logic: mọi search/pricing/inventory/URL/store logic **delegate sang service/interface có sẵn của Magento** (đã được Elasticsuite preference sẵn) — danh mục seam chính xác ở §2b. Public surface = 4 frontend GET routes, input/output allowlist cố định, không nhận GraphQL document, không session, không customer token. Cache HTTP + Magento cache per-store/per-query, invalidation theo event có sẵn (pattern đã chứng minh ở LC-30). LC-30 không đổi trong implementation này; integration llms.txt = ticket sau khi runtime được chấp nhận.

Lựa chọn đã xét: Option A/B (raw + guardrails) — bị TL loại (spec §7b); facade-over-HTTP-to-/graphql — bị loại (không có pattern tồn tại trong repo, thêm hop + parse overhead, mất type safety).

## 2. Files affected (module mới `app/code/Secomm/AiCommerce/`)

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `registration.php`, `etc/module.xml` | new | module skeleton (sequence: Magento_CatalogSearch, Magento_InventorySalesApi, smile elasticsuite — soft) |
| `etc/frontend/routes.xml` | new | route prefix `ai` (frontend area) |
| `etc/config.xml` | new | defaults: enabled=0, max_page_size=50, cache lifetime 3600, allowlist filters |
| `etc/adminhtml/system.xml` + `acl.xml` | new | enable/disable store-scope, caps config (AC: configurable) |
| `Controller/Store/View.php` | new | GET ai/store — thin controller |
| `Controller/Catalog/Search.php` | new | GET ai/catalog/search |
| `Controller/Products/View.php` | new | GET ai/products/{id} (id = SKU) |
| `Controller/Categories/Index.php` | new | GET ai/categories |
| `Model/Config.php` | new | store-scoped config accessor (pattern LC-30 Config) |
| `Model/StoreContext/Resolver.php` | new | validate + resolve store từ `?store=` / header `X-Store` |
| `Service/Input/SearchQueryParser.php` | new | allowlist parse q/category/price_min/price_max/page/page_size/sort/filters |
| `Service/Catalog/SearchService.php` | new | delegate search → fulltext collection |
| `Service/Catalog/ProductFetcher.php` | new | SKU → Product (store-scoped, visibility checks) |
| `Service/Catalog/CategoryTreeService.php` | new | store root → active tree |
| `Service/Pricing/PublicPrice.php` | new | PriceInfo → public price DTO |
| `Service/Inventory/Availability.php` | new | salable>0 ⇒ status only |
| `Service/Url/PublicUrlResolver.php` | new | url_rewrite canonical per store (redirect_type=0, oldest-id tie-break như LC-30 CanonicalPolicy) |
| `Service/Response/ProductDto.php`, `SearchResultDto.php`, `StoreDto.php`, `CategoryDto.php` | new | field-allowlist DTO + JSON serializer |
| `Service/Response/ErrorEnvelope.php` | new | deterministic errors {error:{code,message}} |
| `Model/Cache/ResponseCache.php` + `etc/frontend/events.xml` observers | new | per-store cache + invalidation |
| `Test/Unit/**`, `Test/Integration/**` | new | xem §5 |

**Không sửa**: `app/etc/config.php` (enable bằng `bin/magento module:enable` khi deploy ticket), `Secomm_AiDiscoverability`, core, vendor.

## 2b. Magento reuse seams (exact, code-level)

| Nhu cầu | Seam | Trạng thái |
|---|---|---|
| Search (keyword + filters + sort + paging) | `Magento\CatalogSearch\Model\ResourceModel\Fulltext\CollectionFactory` (create → `setStoreId`, `addSearchFilter(q)`, `addCategoriesFilter`, `addFieldToFilter` cho price/allowlisted attributes, `setCurPage/setPageSize/setOrder`) — collection này bị `Smile\ElasticsuiteCatalog` preference thay engine (`catalog_search/engine = elasticsuite` đã verify) ⇒ **đúng storefront search path** | REUSE |
| Product theo SKU | `Magento\Catalog\Api\ProductRepositoryInterface::get($sku, false, $storeId, false)` | REUSE |
| Product visibility/status checks | `$product->getStatus()`, `getVisibility()` (Visibility constants), store assignment qua url_rewrite/visibility | REUSE |
| Public price | `$product->getPriceInfo()->getPrice(FinalPriceInterface::PRICE_CODE / RegularPriceInterface::PRICE_CODE)->getValue()` + `Magento\Store\Model\Store::getCurrentCurrency()->getCode()` (store-scoped display currency — deterministic theo store, không theo visitor) | REUSE |
| Availability (status only) | `Magento\InventorySalesApi\Api\StockResolverInterface` (website→stock) + `Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::execute($sku, $stockId) > 0` — **giá trị qty dùng nội bộ so sánh, KHÔNG emit** | REUSE |
| Configurable options | `$product->getTypeInstance()->getConfigurableOptions()` / `getConfigurableAttributeCollection` (public attribute only) | REUSE |
| Category tree | `Magento\Catalog\Api\CategoryRepositoryInterface` + `Magento\Store\Model\Group::getRootCategoryId()` (qua StoreManagerInterface) + `getChildren()` recursion | REUSE |
| Canonical URL | `Magento\UrlRewrite\Model\UrlFinderInterface::findOneByData(['entity_type'=>'product'/'category','entity_id'=>..,'store_id'=>..])` với query `redirect_type=0` ordering oldest `url_rewrite_id` — **cùng seam LC-30 CanonicalPolicy đã chứng minh trong repo** (spec §3.5b) | REUSE (thin adapter riêng) |
| Store resolve/validate | `Magento\Store\Api\StoreRepositoryInterface::getActiveStoreByCode($code)` (throw NoSuchEntity → 400; inactive → 400) | REUSE |
| Image URL | `Magento\Catalog\Model\Product\Image\UrlBuilder->getUrl($file, 'category_page_grid')` | REUSE |
| JSON out | `Magento\Framework\Webapi\Rest\Response`? — KHÔNG (area frontend). Dùng `Magento\Framework\Serialize\Serializer\Json` (DI) + Raw result — pattern LC-30 | NEW THIN ADAPTER (controller raw JSON — lý do: frontend area không có webapi response pipeline; LC-30 đã dùng Raw + serializer thành công) |
| Search khi Elasticsuite/index missing | Collection sẽ throw/return empty → catch `\Magento\Framework\Exception\LocalizedException` + `\Smile\ElasticsuiteCore\Exception\InvalidArgumentException` (nếu có) → error envelope `search_unavailable` (503) | NEW THIN ADAPTER (graceful degradation — lý do: agent contract cần deterministic error, không leak stack) |

**Không có NEW business-logic service nào ngoài adapters/validation/DTO ở trên.**

## 3. Route/controller structure (thin controllers)

- `GET /ai/store` → StoreContext Resolver → StoreDto (store_code, locale, currency, base_url)
- `GET /ai/catalog/search?q=&category=&price_min=&price_max=&page=&page_size=&sort=&filter[attr]=v` → SearchQueryParser → SearchService → SearchResultDto
- `GET /ai/products/{sku}` (route param; SKU URL-encoded) → ProductFetcher → ProductDto
- `GET /ai/categories` → CategoryTreeService → CategoryDto[]
- Mọi method khác (POST/PUT/...) → 405 (router chỉ match GET — pattern Router LC-30; controllers implement `HttpGetActionInterface`). Controller chỉ: resolve store → gọi 1 service → serialize → set headers. Không business logic trong controller (rule .ai).

**Store context**: tham số `?store=<code>` hoặc header `X-Store: <code>` (parser ưu tiên param). Valid active store → dùng; missing → **default store của default website** (documented default); invalid/inactive/disabled code → 400 error envelope `invalid_store`. KHÔNG hardcode code nào (spec §3.5).

## 4. Service boundaries + DTO/search mapping/product types/canonical/cache/security

**DTO/response (field allowlist cố định):**
- StoreDto: `store_code, locale, currency, base_url` (không website/group id).
- ProductDto: `sku, name, product_type, public_url, canonical_url (nullable), short_description?, image, price{value,currency,regular_value}, availability{status: in_stock|out_of_stock}, categories[{id,name}], configurable_options[{attribute_code,label,values[{value,label}]}]?` — KHÔNG: qty, cost, supplier, tier price, admin attributes, PII.
- `canonical_url`: null nếu không prove được (luật dưới); `public_url` luôn có (url_rewrite hiện hành) — spec §3.5b/§8: không tự ghép `base_url+url_key`.
- SearchResultDto: `total_count, page, page_size, items[ProductDto (summary subset)]`.

**Search/filter mapping (an toàn):** chỉ nhận các key cho phép. `q` (string, ≤128 chars); `category` (id int); `price_min/max` (decimal, bound 0..10^9); `page` (1..50); `page_size` (1..50, server cap, default 20); `sort` ∈ allowlist `{relevance, price_asc, price_desc, name_asc, name_desc}`; `filter[<attr>]` với attr ∈ config allowlist (mặc định: color, size) mapped qua `addFieldToFilter` trên **store-front filterable** attributes (kiểm tra `is_filterable` khi parse — reject nếu attribute không filterable). Từ chối mọi key lạ → 400 `invalid_parameter`. KHÔNG SearchCriteria injection, KHÔNG nhận GraphQL document, KHÔNG resolver/query từ caller.

**Product types:** simple đầy đủ; configurable: parent + `configurable_options` + giá từ parent (min final price), KHÔNG flatten variants thành product riêng; bundle/grouped: parent + price range (min), children không emit. `product_type` = type_id.

**Canonical URL resolver:** per store: (1) `url_rewrite` row entity/store với `redirect_type=0` (loại alias redirect-history), nhiều row → oldest `url_rewrite_id` (LC-30-proven rule); (2) custom canonical attribute nếu merchant set (check `canonical_url`/`m_seo_canonical` attribute value non-empty — emit thành canonical_url); (3) không prove được → `canonical_url=null`, chỉ `public_url`, kèm documented limitation (Mirasvit conditional canonical — spec §3.5b).

**Cache:** GET-only, no session/cookie (controller không start session — LC-30 pattern). HTTP: `Cache-Control: public, max-age=<lifetime>` + `ETag: sha1(body)` + If-None-Match → 304; `Vary: X-Store` không cần (store là một phần của cache-key nội bộ + URL param). Magento cache `seocomm_aic_store_{id}_{route}_{hash(params)}`, tags `seocomm_aic`, `seocomm_aic_store_{id}`, `cat_p` (core product tag), `cat_c` — reuse core tags ⇒ invalidation tự động theo product/category save; thêm observer cho own config save (pattern LC-30 observers). Không cron.

**Security/input limits (enforce trong app, không tin WAF):** GET-only router match; enabled flag per store (off → 404); query string ≤ 512 chars; số filters ≤ 4; page_size ≤ 50; page ≤ 50; q ≤ 128; sort allowlist; filter attr allowlist + `is_filterable` check; store validation qua StoreRepository; không nhận Authorization/customer token (ignore, KHÔNG authenticate); không cookie dependency; error envelope deterministic `{error:{code,message}}` — KHÔNG stack trace/exception dump; log qua Psr\Log (operation + store, không log query body đầy đủ). Rate limiting = WAF/nginx defense-in-depth (ngoài app).

**Error contract:** 400 `invalid_parameter|invalid_store|invalid_sku_format`, 404 `not_found|disabled` (product không visible/unassigned), 405 method, 503 `search_unavailable` (engine/index), 500 `internal_error` (message chung).

## 5. Test approach (mapping test plan của TL)

- **Unit** (phpunit, pattern LC-30): SearchQueryParser (mỗi key hợp lệ/lạ/cap; sort allowlist; filter allowlist + is_filterable reject), StoreContextResolver (default/valid/invalid/inactive), PublicUrlResolver (oldest redirect_type=0; alias bị loại; store-scope; null khi không prove), PublicPrice (final/regular/currency), Availability (>0 ⇒ in_stock; KHÔNG bao giờ chứa số qty trong DTO — assert), DTO serializer (allowlist — unknown field ⇒ absent), ErrorEnvelope.
- **Integration**: 4 controller happy-path (fixtures: simple/configurable/bundle, 2 store views, disabled + not-visible + store-unassigned products, disabled category), POST → 405, overlong query → 400, page_size 1000 → capped/400, cache hit/miss, store isolation (cache key), invalidation sau product save.
- **Runtime smoke (script như LC-30)**: curl matrix GET/HEAD/POST/PUT/304/store param/X-Store/invalid store/disabled flag 404; so sánh search kết quả với storefront search page (đồng nhất vì cùng engine); assert response KHÔNG chứa qty/cost/stack trace.
- **Regression**: storefront catalog/search, checkout OSC, raw `/graphql` (unchanged — không đụng), LC-30 `/llms.txt` + describedby (unchanged). Perf regression: search response p95 với page_size 50 trên dataset demo < ngưỡng storefront search (không慢 hơn storefront path vì cùng collection).

## 6. Out of scope (non-goals)

Cart/checkout/payment/order/customer/address/coupon/shipping quote, mutation bất kỳ, login/token, exact qty/source/cost/supplier, admin attributes, reviews/PII, UCP/MCP implementation, LC-30 modification, `app/etc/config.php` enable (deploy step riêng), WAF/nginx config (ops ticket riêng), llms.txt integration (ticket sau runtime acceptance).

## 7. Implementation sequence (steps reviewable)

1. Module skeleton + config + ACL — risk low
2. StoreContextResolver + ErrorEnvelope + unit tests — low
3. PublicUrlResolver (+ LC-30-style rewrite query) + unit tests — medium (SEO correctness)
4. PublicPrice + Availability + ProductDto + unit tests — medium (không leak qty)
5. SearchQueryParser + SearchService (Elasticsuite path) + unit/integration — medium-high (search mapping)
6. Controllers + routes + ResponseCache + invalidation observers + integration tests — medium
7. Admin system.xml/i18n (en+vi) + PHPCS 0/0 + di:compile — low
8. Runtime smoke + evidence `.ai/evidence/TASK-QV3R7T/` — low
9. (SAU, ticket riêng khi runtime accepted) LC-30 llms.txt section + contract doc public

## 8. Rollback

Module disabled per store (config) hoặc `module:disable` — không schema DB, không thay đổi core; xóa module là đủ.

## 9. Plan ↔ Spec linkage

Spec SPEC-TASK-QV3R7T §6 (Option C), §7b (TL decision), §8 (data contract/noindex exceptions), §3.5b (canonical evidence), §10 (security boundary), §12 (guardrails /graphql internal), §14 (UCP/MCP shape: DTO fields giữ ánh xạ 1-1 catalog.search/lookup concepts). Nếu implementation phát hiện mâu thuẫn spec → stop, quay lại spec stage (Hard Gate).
