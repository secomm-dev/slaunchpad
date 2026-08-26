# Implementation Plan: LA-22 — AI Commerce Read Layer (Secomm_AiCommerce facade)

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | LA-22 / TASK-QV3R7T |
| Specification | **REQUIRED**: `.ai/specs/SPEC-TASK-QV3R7T-la-22-ai-commerce-read-layer.md` (status: APPROVED FOR IMPLEMENTATION PLANNING; TL decision §7b = Option C NOW) |
| Author | AI Coding (thanhle session) |
| Reviewer (TL) | TL (pending — plan approval trước khi code) |
| Workflow Mode | A |
| Date | 2026-08-24 (rev 2 — TL plan review: 3 blockers resolved: store-context/HTTP cache contract, MSI salability APIs, category tree N+1; + cleanups HEAD/price currency/configurable options/search evidence/cache tags) |

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
| `Model/StoreContext/Resolver.php` | new | validate + resolve store từ query param `store` (V1 canonical — xem §3) |
| `Service/Input/SearchQueryParser.php` | new | allowlist parse q/category/price_min/price_max/page/page_size/sort/filters |
| `Service/Catalog/SearchService.php` | new | delegate search → fulltext collection |
| `Service/Catalog/ProductFetcher.php` | new | SKU → Product (store-scoped, visibility checks) |
| `Service/Catalog/CategoryTreeService.php` | new | 1 category collection load + build tree in-memory (xem §4c) |
| `Service/Pricing/PublicPrice.php` | new | PriceInfo → public price DTO |
| `Service/Inventory/Availability.php` | new | IsProductSalableInterface / AreProductsSalableInterface ⇒ status only (xem §4b) |
| `Service/Url/PublicUrlResolver.php` | new | url_rewrite canonical per store (redirect_type=0, oldest-id tie-break như LC-30 CanonicalPolicy) |
| `Service/Response/ProductDto.php`, `SearchResultDto.php`, `StoreDto.php`, `CategoryDto.php` | new | field-allowlist DTO + JSON serializer |
| `Service/Response/ErrorEnvelope.php` | new | deterministic errors {error:{code,message}} |
| `Model/Cache/ResponseCache.php` + `etc/frontend/events.xml` observers | new | per-store cache + invalidation |
| `Test/Unit/**`, `Test/Integration/**` | new | xem §5 |

**Không sửa**: `app/etc/config.php` (enable bằng `bin/magento module:enable` khi deploy ticket), `Secomm_AiDiscoverability`, core, vendor.

## 2b. Magento reuse seams (exact, code-level)

| Nhu cầu | Seam | Trạng thái |
|---|---|---|
| Search (keyword + filters + sort + paging) | `Magento\CatalogSearch\Model\ResourceModel\Fulltext\CollectionFactory` (create → `setStoreId`, `addSearchFilter(q)`, `addCategoriesFilter`, `addFieldToFilter` cho price/allowlisted attributes, `setCurPage/setPageSize/setOrder`). **Evidence (đã verify trong vendor bản này)**: `module-elasticsuite-catalog/etc/di.xml:87-88` — preference `Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection` → `Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection`, nên CollectionFactory luôn trả về collection Elasticsuite; storefront Layer (frontend/di.xml:157-178) chính inject `Magento\CatalogSearch\Model\ResourceModel\Fulltext\CollectionFactory` này ⇒ **đúng storefront search path**. Các method cần dùng đều tồn tại trên Smile collection: `addSearchFilter` (line 374), `setOrder` (215, mapped qua SearchRequest sort), `addFieldToFilter` (272, price/attribute filters map qua filter builder), `setCurPage`/`setPageSize` (241/254), `addCategoriesFilter` (core Product\Collection:971) | REUSE |
| Product theo SKU | `Magento\Catalog\Api\ProductRepositoryInterface::get($sku, false, $storeId, false)` | REUSE |
| Product visibility/status checks | `$product->getStatus()`, `getVisibility()` (Visibility constants), store assignment qua url_rewrite/visibility | REUSE |
| Public price | `$product->getPriceInfo()->getPrice(FinalPriceInterface::PRICE_CODE)->getValue()` **KHÔNG dùng trực tiếp cho response** — evidence: `Magento\Framework\Pricing\Price\AbstractPrice::getValue()` pipeline gọi `priceCurrency->convertAndRound($amount)` **không có scope/currency** (PriceCurrencyInterface::convert signature `($amount, $scope, $currency)`), tức dùng *current* currency của request (visitor-switchable) ⇒ không deterministic cho agent. Facade gọi lại `PriceCurrencyInterface::convertAndRound($rawAmount, $storeId, $store->getDefaultCurrencyCode())` **một lần duy nhất** với base amount + store default currency — không double-convert (lấy raw từ PriceInfo amount trước convert, hoặc re-convert từ base một chiều), currency code emit = đúng currency đã convert | REUSE (với explicit scope/currency) |
| Availability (status only) | **Primary**: `Magento\InventorySalesApi\Api\IsProductSalableInterface` (single product page) — preference thực `IsProductSalableConditionChain` (module-inventory-sales/etc/di.xml:61), chain gồm composite-aware conditions (`IsSetInStockStatusForCompositeProductCondition`, `IsSalableWithReservationsCondition`, `ManageStockCondition`, `BackOrderCondition`, `IsAnySourceItemInStockCondition`) ⇒ xử lý đúng simple/configurable/bundle/grouped theo rule Magento, không tự chế parent-quantity rule. **Batch cho search list**: `Magento\InventorySalesApi\Api\AreProductsSalableInterface::execute(array $skus, int $stockId)` (preference `AreProductsSalable`, di.xml:59). StockId từ `Magento\InventorySalesApi\Api\StockResolverInterface::execute(websiteCode, sku=null)→getStockId()` — resolve 1 lần mỗi request, cache trong object. **Không dùng `GetProductSalableQtyInterface`** (TL blocker 2: có thể throw với composite/source-managed types, và không phải boolean contract). Output chỉ `in_stock|out_of_stock` — qty không bao giờ emit | REUSE |
| So sánh chéo với storefront truth | GraphQL `stock_status` resolver `Magento\CatalogInventoryGraphQl\Model\Resolver\StockStatusProvider` dùng `StockStatusRepositoryInterface->get($productId)` (legacy stock_status, MSI sync qua `LegacyStockStatusStorage`/indexer). Facade chọn MSI-native salability chain (số lượng reservations + composite rules) làm truth — bằng chứng 2 path đồng bộ bởi MSI indexer; nếu spike runtime thấy lệch giữa 2 path trên fixture composite → ưu tiên MSI chain và ghi nhận documented difference | EVIDENCE |
| Configurable options | `Magento\ConfigurableProduct\Api\OptionRepositoryInterface::getList($sku)` — **service API ổn định**, thay cho `$product->getTypeInstance()` magic call (blocker cleanup #3). Values option label lấy từ `ConfigurableProductManagementInterface` hoặc attribute option labels store-scoped | REUSE |
| Category tree | `Magento\Catalog\Model\ResourceModel\Category\CollectionFactory` → **một query duy nhất**: `setStoreId($storeId)`, `addAttributeToSelect(['name','url_key','is_active','include_in_menu'])` (explicit fields), `addPathsFilter($rootCategory->getPath() . '/%')` (chỉ trong tree của store), rồi build tree in-memory bằng `path`/`parent_id` (các field đã select). Filter active + is_active=1 trong SQL (`addIsActiveFilter`). **KHÔNG** dùng `CategoryRepositoryInterface` recursion (N+1 — TL blocker 3). Bound: depth limit cấu hình được (default 5 levels, skip root container levels 0/1). Root id từ `Magento\Store\Model\Store::getRootCategoryId()` (store group) | REUSE |
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
- Mọi method khác (POST/PUT/DELETE/PATCH...) → 405 (router chỉ match GET/HEAD — pattern Router LC-30; controllers implement `HttpGetActionInterface`). Controller chỉ: resolve store → gọi 1 service → serialize → set headers. Không business logic trong controller (rule .ai).
- **HEAD**: được hỗ trợ, cùng headers với GET (Cache-Control/ETag/Content-Type), body rỗng — nhất quán với LC-30 (GET/HEAD-only) và HTTP cache semantics. Runtime smoke test matrix bao gồm HEAD 200 + HEAD 304.

**Store context contract (V1 — TL blocker 1 resolved)**: **chỉ query parameter `?store=<code>` là cơ chế chọn store duy nhất**; header `X-Store` **KHÔNG được hỗ trợ trong V1** (bỏ hẳn, không accept — tránh Vary ambiguity và CDN cache poisoning risk). Lý do: cache identity hiện diện trong URL ⇒ CDN/proxy cache an toàn mặc định, agent dùng đơn giản, không cần `Vary`. Valid active store → dùng; missing → **default store của default website** (documented default, vẫn hiện trong response `store_code` nên agent luôn biết scope); invalid/inactive/disabled code → 400 error envelope `invalid_store`. KHÔNG hardcode code nào (spec §3.5). `Vary` header: không cần cho store (store ∈ URL); không emit header nào phụ thuộc request body.

## 4. Service boundaries + DTO/search mapping/product types/canonical/cache/security

**DTO/response (field allowlist cố định):**
- StoreDto: `store_code, locale, currency, base_url` (không website/group id).
- ProductDto: `sku, name, product_type, public_url, canonical_url (nullable), short_description?, image, price{value,currency,regular_value}, availability{status: in_stock|out_of_stock}, categories[{id,name}], configurable_options[{attribute_code,label,values[{value,label}]}]?` — KHÔNG: qty, cost, supplier, tier price, admin attributes, PII.
- `canonical_url`: null nếu không prove được (luật dưới); `public_url` luôn có (url_rewrite hiện hành) — spec §3.5b/§8: không tự ghép `base_url+url_key`.
- SearchResultDto: `total_count, page, page_size, items[ProductDto (summary subset)]`.

**Search/filter mapping (an toàn):** chỉ nhận các key cho phép. `q` (string, ≤128 chars); `category` (id int); `price_min/max` (decimal, bound 0..10^9); `page` (1..50); `page_size` (1..50, server cap, default 20); `sort` ∈ allowlist `{relevance, price_asc, price_desc, name_asc, name_desc}`; `filter[<attr>]` với attr ∈ config allowlist (mặc định: color, size) mapped qua `addFieldToFilter` trên **store-front filterable** attributes (kiểm tra `is_filterable` khi parse — reject nếu attribute không filterable). Từ chối mọi key lạ → 400 `invalid_parameter`. KHÔNG SearchCriteria injection, KHÔNG nhận GraphQL document, KHÔNG resolver/query từ caller.

**Product types:** simple đầy đủ; configurable: parent + `configurable_options` + giá từ parent (min final price), KHÔNG flatten variants thành product riêng; bundle/grouped: parent + price range (min), children không emit. `product_type` = type_id.

**Canonical URL resolver:** per store: (1) `url_rewrite` row entity/store với `redirect_type=0` (loại alias redirect-history), nhiều row → oldest `url_rewrite_id` (LC-30-proven rule); (2) custom canonical attribute nếu merchant set (check `canonical_url`/`m_seo_canonical` attribute value non-empty — emit thành canonical_url); (3) không prove được → `canonical_url=null`, chỉ `public_url`, kèm documented limitation (Mirasvit conditional canonical — spec §3.5b).

**Cache:** GET/HEAD-only, no session/cookie (controller không start session — LC-30 pattern). HTTP: `Cache-Control: public, max-age=<lifetime>` + `ETag: sha1(body)` + If-None-Match → 304. Vì store nằm trong URL, không cần `Vary` — cache identity hoàn toàn nằm trong URL (TL blocker 1).

**Magento internal cache + invalidation (TL blocker 5 — explicit tags, không dựa core tag propagation):** entry key `secomm_aic_store_{id}_{route}_{hash(params)}`, lưu qua `Magento\Framework\App\CacheInterface::save($data, $key, $tags, $lifetime)`. Tags **chủ quan của module** (an toàn vì Magento `CacheInterface::cleanByTags` match trên tag string ta tự lưu — không phụ thuộc cách core gắn tag cho entry riêng của mình): `seocomm_aic` (tổng), `seocomm_aic_store_{storeId}`, `seocomm_aic_p_{productId}` (response chứa product), `seocomm_aic_c_{categoryId}` (response chứa category). Invalidation bằng observers của module trên event có sẵn: `catalog_product_save_after` / `catalog_product_delete_after` → clean `seocomm_aic_p_{id}`; `catalog_category_save_after` / `catalog_category_move_after` → clean `seocomm_aic_c_{id}` + `seocomm_aic` (category ảnh hưởng tree/search rộng); config save (`admin_system_config_changed_section_...`) → clean `seocomm_aic`. Đây là **bounded, entity-specific policy** — không reuse tag `cat_p`/`cat_c` cho entry của mình (tag core được clean bởi indexer với scope/lifecycle khác, không đảm bảo覆盖 custom entry). Không cron.

**Security/input limits (enforce trong app, không tin WAF):** GET-only router match; enabled flag per store (off → 404); query string ≤ 512 chars; số filters ≤ 4; page_size ≤ 50; page ≤ 50; q ≤ 128; sort allowlist; filter attr allowlist + `is_filterable` check; store validation qua StoreRepository; không nhận Authorization/customer token (ignore, KHÔNG authenticate); không cookie dependency; error envelope deterministic `{error:{code,message}}` — KHÔNG stack trace/exception dump; log qua Psr\Log (operation + store, không log query body đầy đủ). Rate limiting = WAF/nginx defense-in-depth (ngoài app).

**Error contract:** 400 `invalid_parameter|invalid_store|invalid_sku_format`, 404 `not_found|disabled` (product không visible/unassigned), 405 method, 503 `search_unavailable` (engine/index), 500 `internal_error` (message chung).

## 5. Test approach (mapping test plan của TL)

- **Unit** (phpunit, pattern LC-30): SearchQueryParser (mỗi key hợp lệ/lạ/cap; sort allowlist; filter allowlist + is_filterable reject; **reject key lạ + reject `store` value không phải store code dạng [a-z0-9_]+**), StoreContextResolver (missing → default store; valid → dùng; invalid/inactive → 400; **X-Store header bị ignore hoàn toàn**), PublicUrlResolver (oldest redirect_type=0; alias bị loại; store-scope; null khi không prove), PublicPrice (final/regular; **convert một lần với store default currency; không double-convert — assert giá trị đúng rate**), Availability (mock IsProductSalableInterface chain: true/false; **assert DTO không có trường qty/salable_qty ở mọi level**; composite: configurable out-of-stock khi mọi variant not-salable qua chain — dùng fixture thật trong integration), DTO serializer (allowlist — unknown field ⇒ absent), ErrorEnvelope.
- **Integration**: 4 controller happy-path (fixtures: simple/configurable/bundle/grouped — mỗi loại có case in-stock và out-of-stock, 2 store views, disabled + not-visible + store-unassigned products, disabled category + category ngoài store tree), POST/PUT → 405, HEAD → 200 cùng headers/empty body, overlong query → 400, page_size 1000 → capped/400, batch availability (search list N products ⇒ **assert chỉ 1 call `AreProductsSalableInterface::execute`** với N sku — spy mock), cache hit/miss, **store isolation ở cả 2 tầng**: (a) Magento cache key khác nhau cho `?store=a` vs `?store=b`, (b) HTTP: response body khác + không cross-contamination khi request liên tiếp không cookie, invalidation sau product save (tag `secomm_aic_p_{id}` bị clean, entry khác giữ).
- **Runtime smoke (script như LC-30)**: curl matrix GET/HEAD/POST/PUT/304 (ETag reuse)/`?store=` valid/invalid/missing/`X-Store` header bị ignore (response vẫn default store, không đổi)/disabled flag 404; so sánh search kết quả với storefront search page (đồng nhất vì cùng engine/collection); assert response KHÔNG chứa qty/cost/salable/source/stack trace.
- **Regression**: storefront catalog/search, checkout OSC, raw `/graphql` (unchanged — không đụng), LC-30 `/llms.txt` + describedby (unchanged). Perf regression: search response p95 với page_size 50 trên dataset demo < ngưỡng storefront search (không慢 hơn storefront path vì cùng collection).

## 5b. Spike / evidence log (plan-phase, chỉ đọc — chưa có production code)

1. **Search/Elasticsuite (blocker cleanup #4)**: verified trong vendor bản này — `smile/module-elasticsuite-catalog/etc/di.xml:87-88` preference Fulltext\Collection → Smile collection; storefront Layer frontend/di.xml:157-178 dùng đúng `Magento\CatalogSearch\Model\ResourceModel\Fulltext\CollectionFactory`; các method `addSearchFilter`(:374)/`setOrder`(:215)/`addFieldToFilter`(:272)/`setCurPage`(:241)/`setPageSize`(:254) tồn tại trên Smile collection (q/category/price/filterable/sort/paging đi qua SearchRequest của Elasticsuite). DB đã verify `catalog_search/engine = elasticsuite`. Spike runtime cụ thể (search thật với filter+sort) nằm ở plan step 5 trước khi viết controller.
2. **Pricing currency (blocker cleanup #2)**: verified `Magento\Framework\Pricing\Price\AbstractPrice` — `getValue()` pipeline gọi `priceCurrency->convertAndRound($amount)` **không scope/currency** ⇒ dùng current-currency theo request (visitor-switchable). Vì vậy facade **không lấy getValue() làm response**; tự convert từ amount với `($storeId, $store->getDefaultCurrencyCode())` — không double-convert vì chỉ convert một chiều một lần.
3. **Availability**: verified `module-inventory-sales/etc/di.xml:59,61` — `AreProductsSalableInterface` → `AreProductsSalable` (loop `IsProductSalableInterface` — API ổn định, facade gọi 1 lần cho N sku; nhận thức rõ implementation 2.4.8 không batch SQL, không N-call từ phía facade; nếu perf smoke thấy chậm thì ghi nhận optimization LATER); `IsProductSalableInterface` → `IsProductSalableConditionChain` (composite-aware). GraphQL truth-path `StockStatusProvider` (dùng StockStatusRepository) được ghi làm bằng chứng chéo.
4. **Category tree**: verified `Magento\Catalog\Model\ResourceModel\Category\Collection` có `addIsActiveFilter`(:486), `addPathsFilter`(:524), `addAttributeToSelect` explicit fields — 1 query dùng được cho toàn tree.
5. **Configurable options**: verified `Magento\ConfigurableProduct\Api\OptionRepositoryInterface` (+ `LinkManagementInterface`) tồn tại — service API, bỏ type-instance magic.

## 6. Out of scope (non-goals)

Cart/checkout/payment/order/customer/address/coupon/shipping quote, mutation bất kỳ, login/token, exact qty/source/cost/supplier, admin attributes, reviews/PII, UCP/MCP implementation, LC-30 modification, `app/etc/config.php` enable (deploy step riêng), WAF/nginx config (ops ticket riêng), llms.txt integration (ticket sau runtime acceptance).

## 7. Implementation sequence (steps reviewable)

1. Module skeleton + config + ACL — risk low
2. StoreContextResolver + ErrorEnvelope + unit tests — low
3. PublicUrlResolver (+ LC-30-style rewrite query) + unit tests — medium (SEO correctness)
4. PublicPrice (explicit scope/currency) + Availability (IsProductSalable/AreProductsSalable) + ProductDto + unit tests — medium (không leak qty; composite fixtures)
5. SearchQueryParser + SearchService (Elasticsuite path) + unit/integration — medium-high (search mapping); **spike runtime trước controller**: search thật với q+category+price+filter+sort qua Fulltext Collection trong dev container, so kết quả với storefront — ghi evidence vào `.ai/evidence/TASK-QV3R7T/`
6. Controllers + routes + ResponseCache + invalidation observers + integration tests — medium
7. Admin system.xml/i18n (en+vi) + PHPCS 0/0 + di:compile — low
8. Runtime smoke + evidence `.ai/evidence/TASK-QV3R7T/` — low
9. (SAU, ticket riêng khi runtime accepted) LC-30 llms.txt section + contract doc public

## 8. Rollback

Module disabled per store (config) hoặc `module:disable` — không schema DB, không thay đổi core; xóa module là đủ.

## 9. Plan ↔ Spec linkage

Spec SPEC-TASK-QV3R7T §6 (Option C), §7b (TL decision), §8 (data contract/noindex exceptions), §3.5b (canonical evidence), §10 (security boundary), §12 (guardrails /graphql internal), §14 (UCP/MCP shape: DTO fields giữ ánh xạ 1-1 catalog.search/lookup concepts). Nếu implementation phát hiện mâu thuẫn spec → stop, quay lại spec stage (Hard Gate).
