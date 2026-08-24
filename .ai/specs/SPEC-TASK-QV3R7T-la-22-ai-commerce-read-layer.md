# [SLP][TASK-QV3R7T] LA-22 — AI Commerce Read Layer (Audit / Architecture Decision)

Specification ID: SPEC-TASK-QV3R7T

> **External ref**: LA-22 · **Mode**: A (spec-first) · **Status**: DRAFT v2 (TL review pass 2 — đã hiệu chỉnh 3 blockers: UCP/MCP maturity 2026, quyết định publication raw `/graphql`, canonical URL contract từ url_rewrites). **Không có production code.** Audit + architecture decision only.
> **Scope guard**: Layer-2 anonymous READ-ONLY commerce discovery. OUT: MCP/UCP implementation, cart/checkout/payment mutation, customer data, agent autonomous purchase, thay đổi LC-30.

---

## 1. Problem

LC-30 (`Secomm_AiDiscoverability`, SPEC-TASK-0X552E) đã cung cấp Layer-1: discovery document (`/llms.txt`) với curated URLs, locale/currency, public title. Nhưng AI agent sau khi discover storefront **không có contract công khai nào để đọc dữ liệu commerce** (sản phẩm, giá, tồn kho, category) một cách machine-readable, bounded, an toàn. Câu hỏi kiến trúc: Magento 2.4.8 đã expose gì, cái gì an toàn cho anonymous agent, có cần module/API mới không.

## 2. Business goal

Cho phép AI agent trả lời được các câu hỏi commerce công khai (tìm sản phẩm, xem giá/availability, liệt kê category, lấy canonical URL, biết store context) **bằng năng lực Magento sẵn có**, không duplicate API, không mở mutation/PII, tối thiểu chi phí bảo trì.

## 3. Current-state audit (có bằng chứng, audit 2026-08-24 @ `18c0f661`, env local `https://webhook.thanhaloha.io.vn/`)

### 3.1 Magento GraphQL — ĐÃ MỞ anonymous, hoạt động đầy đủ

Enabled (app/etc/config.php): `Magento_GraphQl`, `GraphQlCache`, `GraphQlResolverCache`, `CatalogGraphQl`, `StoreGraphQl`, `CatalogInventoryGraphQl`, `InventoryGraphQl`, `ConfigurableProduct` (qua Catalog), `BundleGraphQl`, `UrlRewriteGraphQl`, `CatalogUrlRewriteGraphQl`, `DirectoryGraphQl`, `Smile_ElasticsuiteCatalogGraphQl`, …

Live-probe anonymous (không token, không cookie):

| Query | Kết quả |
|---|---|
| `storeConfig { store_code locale base_currency_code default_display_currency_code base_url }` | 200 — `default / vi_VN / EUR / EUR` |
| `products(search:"bag", pageSize:2) { total_count items{ sku name url_key stock_status price{regularPrice{amount{value currency}}} } }` | 200 — 2 items, price + `IN_STOCK/OUT_OF_STOCK` |
| `categoryList { id name url_path children }` | 200 — full category tree |
| `Store: us_en` header | 200 — resolve store view theo header (multi-store OK) |
| Introspection `__schema` | **200 — đang mở** |

Điểm chú ý: products query qua Elasticsearch/Elasticsuite — nếu index chưa build sẽ lỗi `catalog_product index does not exist yet` (đã reindex trong audit; đây là dependency vận hành, không phải bug).

### 3.2 Magento REST — KHÔNG anonymous cho catalog

`GET /rest/V1/products?searchCriteria...` → 401 `The consumer isn't authorized to access %resources%` (ACL `Magento_Catalog::products`). REST catalog yêu cầu Integration/Admin token → **không phù hợp public AI agent** (không có cơ chế anonymous token phân phối được).

### 3.3 Storefront machine-readable surfaces sẵn có

- **JSON-LD trên product page**: `application/ld+json` schema.org `Product` + `Offer` (name, sku, description, price, priceCurrency, url) — verified live. (Nguồn: Hyva/Mirasvit SeoMarkup theme output.)
- **Sitemap**: `Mirasvit_SeoSitemap` (preference lên core `Sitemap`, cron `sitemap_generate`, per-store) — LC-30 đã tham chiếu.
- **Canonical**: do Mirasvit `Canonical.php` observer sinh (request-bound — xem SPEC-TASK-0X552E §0.A).

### 3.4 Search / Inventory / Pricing (hành vi core)

- Search: OpenSearch + `Smile_Elasticsuite*` full suite; GraphQL `products(search/filter/sort/pageSize)` dùng cùng search engine với storefront → kết quả agent = kết quả storefront (không lệch).
- Inventory: GraphQL expose `stock_status` (IN_STOCK/OUT_OF_STOCK) per product + `only_x_left_in_stock` (**chỉ render khi config `inventory_options/stock_threshold` được set** — mặc định tắt → không lộ số chính xác). MSI salable quantity KHÔNG có trong anonymous GraphQL query fields mặc định.
- Pricing: `price { regularPrice { amount { value currency } } }` theo store display currency; `minimal_price`, `special_price`, tier price chỉ hiện khi được gán cho group của guest (NOT LOGGED IN). Tax display theo store config. Configurable/bundle/grouped có price range.

### 3.5 Multi-store

GraphQL resolution order: `Store` header → store cookie → default. Giá, locale, category tree, name, URL theo store view. ĐÃ VERIFY live (header `Store: us_en` → `storeConfig.store_code = us_en`).

> **Label bằng chứng**: các probe dùng env LOCAL (`webhook.thanhaloha.io.vn`) với store codes `default/us_en/vi_vn/eu_en/fr_fr`. Demo/prod Launchpad có store codes KHÁC (remote thực tế: `default` + `launchpad_en`). **Agent contract phải DISCOVER store codes lúc runtime** (qua LC-30 `/llms.txt` per-store hoặc `storeConfig`) — không hardcode `us_en` hay bất kỳ code nào làm "English code chính thức của Launchpad".

### 3.5b Canonical URL audit (bổ sung v2 — blocker 3)

Schema ProductInterface trong repo này (introspection live) có: `canonical_url`, `url_key`, `url_suffix`, `url_rewrites[{url}]`. CategoryInterface có: `canonical_url`, `url_key`, `url_path` (deprecated), `url_suffix`. `route(url)` query (UrlRewriteGraphQl) resolve entity theo URL — verified (`route(url:"mjuk-mohair-throw.html") → SimpleProduct`).

Live-probe kết quả (default store):

| Entity | canonical_url | url_key + suffix | url_rewrites |
|---|---|---|---|
| Simple `throw-mohair` | **null** | `mjuk-mohair-throw.html` | `[{url:"mjuk-mohair-throw.html"}]` |
| Bundle `bundle-new-home-gift` | **null** | `bundle-new-home-gift.html` | 1 rewrite trùng url_key |
| Configurable `bedding-linen` | **null** | `linen-duvet-set.html` | `[{url:"linen-duvet-set.html"}]` |
| Category `Gear/...` | **null** | `url_path=gear`, suffix `.html` | — |

Store-view scoping: cùng configurable `bedding-linen` query với `Store: us_en` → `url_rewrites: []` (product không gán/visible ở store đó) — chứng minh `url_rewrites` là **nguồn rewrite theo store view**, rỗng = không truy cập công khai ở store đó.

So sánh storefront: `<link rel="canonical">` của product page = `https://…/mjuk-mohair-throw.html` — **khớp** `url_rewrites[0]` + `storeConfig.base_url` ở các case đã probe (không có conditional Mirasvit canonical rewrite nào áp lên các entity mẫu này).

**Kết luận canonical**: (1) `canonical_url` tồn tại trong schema nhưng **null trong install này** (chỉ populate khi có custom canonical attribute/request path) — không thể là nguồn duy nhất; (2) `base_url + url_key + url_suffix` KHÔNG được dùng làm canonical logic chính thức (bỏ qua rewrites/store prefix/Mirasvit); (3) nguồn chuẩn cho agent contract = **`url_rewrites` theo store view** (entry khớp `url_key+url_suffix` là canonical hiện hành; các entry khác là alias/redirect history); (4) Mirasvit **conditional** canonical rewrite rules (regex trên current URL/registry) KHÔNG thể tái lập offline — đã chứng minh ở LC-30 §0.A; nếu merchant cấu hình conditional canonical thì GraphQL url_rewrites CÓ THỂ KHÁC canonical storefront → **documented limitation** (§8), không tự phát minh URL composition.

### 3.6 Custom/third-party effects trên surface này (audit app/code toàn bộ — chi tiết ở §11 Security)

- **KHÔNG có** custom plugin/preference nào trên `products`/`categoryList`/`storeConfig` resolver hay `ProductRepository` price path (chỉ 1 Mirasvit plugin capture URL on delete — không ảnh hưởng read).
- `Secomm_AddressDropdown` thêm GraphQL queries `GetListCity/GetListSubCity` (dữ liệu địa lý công khai, `cacheable:false`).
- `Magefan_BlogGraphQl` thêm blog queries + storeConfig extension data (config values only).
- **Phát hiện security-adjacent (ngoài scope LA-22 nhưng phải ghi nhận)**: `Mageplaza_Smtp` GraphQL query `mpSmtpBestsellers(app_id, secret_key)` — bestseller data, auth bằng string args trong query (nếu secret lộ trong log/history thì bypass); Mageplaza OSC webapi anonymous guest-cart routes (incl. `isEmailAvailable` — email enumeration), `mp-abandoned-cart/recovery` anonymous; **không có rate limiting tùy chỉnh nào** trong app/code.

## 4. Use cases (bounded, anonymous, read-only)

| UC | Câu hỏi | Thỏa bằng |
|---|---|---|
| UC-01 | "Find black dresses under 2,000,000 VND" | GraphQL `products(filter:{category_id, price:{to}, color...})` |
| UC-02 | "Product details for SKU/URL X" | `products(filter:{sku})` / `products(filter:{url_key})` |
| UC-03 | "Collections available?" | `categoryList` |
| UC-04 | "Product currently available?" | `stock_status` (boolean semantics, không lộ số exact) |
| UC-05 | "Current public price in this store/currency?" | `price.regularPrice.amount` + `storeConfig.default_display_currency_code` |
| UC-06 | "Products by size/color/category" | `products(filter:{...})` qua Elasticsuite attributes |
| UC-07 | "Canonical storefront URLs" | `url_key` + store `base_url` (hoặc `url_rewrites` qua UrlRewriteGraphQl) |
| UC-08 | "Store locale/currency/context" | `storeConfig` + LC-30 `/llms.txt` |

**OUT OF SCOPE (không bao giờ trong Layer-2):** customer account/profile/addresses, order history, cart mutation, checkout mutation, coupon, shipping quote cần địa chỉ cá nhân, payment, order creation, agent autonomous purchase. GraphQL mutation fields tồn tại trên endpoint nhưng nằm ngoài agent contract (§8).

## 5. Non-goals

- Không implement MCP/UCP/ACP server. Không chatbot. Không expose cart/checkout cho agent.
- Không sửa LC-30 trong task này (chỉ định nghĩa integration point tương lai, §13).
- Không tạo API thứ hai duplicate Magento GraphQL/REST.
- Không expose exact warehouse quantity, cost, margin, supplier, admin attributes, unpublished products.

## 6. Architecture decision matrix (v2 — tách INTERNAL READ SOURCE khỏi PUBLIC AGENT SURFACE)

Phân biệt bắt buộc: **"Magento GraphQL là internal data source tốt nhất"** ≠ **"raw `/graphql` phải là endpoint công khai cho agent"**. Publication decision là decision về **discovery/exploitation exposure**, không chỉ technical exposure: endpoint đã tồn tại (technical), nhưng công bố nó như contract chính thức làm tăng discoverability, automation và traffic kỳ vọng lên toàn bộ mutation surface mà audit đã ghi nhận (introspection mở, guest-cart mutations anonymous, `generateCustomerToken`, `mpSmtpBestsellers`, không rate limiter riêng).

### A — Raw Magento GraphQL, quảng bá trực tiếp (từ `/llms.txt`)

| Tiêu chí | Đánh giá |
|---|---|
| Feasibility now | Cao (đã mở anonymous) |
| Security boundary | **KHÔNG** — agent thấy toàn schema + mọi mutation fields; không thể loại mutation cho anonymous ở mức Magento core |
| Code thêm | 0 |
| Duplicate logic risk | 0 |
| Cacheability | Resolver cache OK; GET queries cache được nhưng POST arbitrary thì không |
| Multi-store | OK (Store header) |
| Agent interoperability | Cao (schema tự mô tả) nhưng contract không ổn định với agent (schema lớn, field rủi ro lẫn field an toàn) |
| Migration path | Không kiểm soát được khi muốn thu hẹp sau này (đã advertise full) |
| **Verdict** | **REJECT NOW** — không quảng bá raw `/graphql` khi chưa có security model chứng minh được (§10, §12) |

### B — Magento GraphQL + operational guardrails (WAF/rate limit/depth/GET-cache), công bố contract curated

| Tiêu chí | Đánh giá |
|---|---|
| Feasibility now | Cao — guardrail thuộc nginx/WAF/Cloudflare + core config; GraphQL GET caching dùng được cho canned read queries |
| Security boundary | **Một phần, ở lớp ops**: rate limit + depth/complexity + chặn operation rõ ràng không mong muốn; mutation KHÔNG loại được kỹ thuật per-field cho anonymous (Magento core không hỗ trợ disable mutation theo customer-type) — chỉ chặn được bằng WAF pattern heuristics (POST mutation signatures) → không airtight |
| Code thêm | 0 (config + infra) |
| Duplicate logic risk | 0 |
| Cacheability | GET + persisted-shape queries cache ở CDN được |
| Multi-store | OK |
| Agent interoperability | Cao |
| Migration path | Có thể nâng cấp lên C/D sau vì contract curated đã giới hạn field |
| **Verdict** | **NOW (có điều kiện)** — chỉ khi checklist §12 đạt; mutation-blocking heuristic phải được TL chấp nhận là best-effort |

### C — Read-only constrained facade/gateway (module Secomm, dùng GraphQL/services nội bộ)

| Tiêu chí | Đánh giá |
|---|---|
| Feasibility now | Trung bình — module mới (router/controller hoặc GraphQL schema riêng, ACL anonymous, tests) |
| Security boundary | **Đầy đủ kỹ thuật**: chỉ read, chỉ field allowlist, pageSize/depth ép server-side, không mutation surface, schema đóng, không introspection rủi ro; JSON/HTTP dễ cache + rate limit per route |
| Code thêm | Vừa (1 module bounded) |
| Duplicate logic risk | **Thấp nếu facade DELEGATE** sang GraphQL/resolver nội bộ (không re-implement search/pricing/canonical — reuse `products` query internals / `SearchCriteria` + `url_rewrites`) — facade là SECURITY BOUNDARY, không phải data duplication |
| Cacheability | Cao (GET, ETag, CDN) |
| Multi-store | OK (Store header/context nội bộ) |
| Agent interoperability | Cao (contract nhỏ, ổn định, versioned) |
| Migration path | Là nền sẵn cho adapter D/E |
| **Verdict** | **LATER nếu B không đạt guardrail** hoặc khi cần SLA/stable-schema guarantee. Không tự động chọn, nhưng không loại chỉ vì "duplication" — justification đúng là security boundary |

### D — UCP Catalog adapter (`/.well-known/ucp`, `catalog.search`/`catalog.lookup`) trên internal read source

| Tiêu chí | Đánh giá |
|---|---|
| Feasibility now | Kỹ thuật khả thi (UCP stable 2026-04-08, catalog capabilities + MCP bindings) nhưng chưa có sẵn adapter Magento chính thức đã chứng minh trong repo; phải tự build trên B/C |
| Security boundary | Theo surface nó wrap (B hoặc C) |
| Code thêm | Cao (protocol adapter) |
| Duplicate logic risk | Thấp nếu là adapter mỏng trên contract đã có |
| Multi-store | OK nếu adapter nhận store context |
| Agent interoperability | Cao nhất (chuẩn mở, có governance — Google/Shopify/Stripe + Amazon/Meta/Microsoft/Salesforce tham gia kỹ thuật) |
| Migration path | Phải có B/C trước; KHÔNG đụng core data path (adapter gọi read contract) |
| **Verdict** | **LATER (preferred future interop layer)** — xem §14 |

### E — MCP transport (via UCP MCP bindings)

- Transport/tool-composition trên read source; giá trị thật khi có tool orchestration + transaction. **LATER**, theo UCP.

**REST (option cũ B của v1): vẫn REJECT cho anonymous agent** — 401 đã verify (§3.2); không đổi trừ khi audit mới chứng minh ngược.

## 7. Recommended architecture (v2)

**Kiến trúc 2 lớp, tách bạch:**

```
Magento GraphQL / services (INTERNAL COMMERCE READ SOURCE — không đổi, không code mới)
        ↓  safe bounded read contract (field allowlist, url_rewrites canonical, pageSize caps)
TODAY:  approved machine interface = Option B (curated GraphQL + enforced guardrails)
        ↓  khi trigger xảy ra (§14.3)
FUTURE: UCP catalog adapter (/.well-known/ucp) và/hoặc MCP bindings — adapter mỏng, KHÔNG đổi data path
```

Quyết định NOW: **Option B có điều kiện** — internal source = core GraphQL; public surface = curated GraphQL contract CHỈ được công bố sau khi guardrails §12 demonstrably in place. **HARD RULE**: LA-22 implementation KHÔNG được quảng bá raw `/graphql` trong `/llms.txt` cho tới khi (1) rate limiting trên `/graphql` hoạt động ở nginx/WAF/Cloudflare, (2) depth/complexity config bật và verify, (3) mutation-blocking heuristic (best-effort) hoặc facade C được duyệt, (4) `mpSmtpBestsellers` xử lý xong. Nếu B không đạt sau 1 chu kỳ đánh giá → chuyển C (facade) làm public surface, vẫn giữ GraphQL làm internal source.

Component:
- **Agent Contract document** (versioned): canned queries, field allowlist (§8), pagination cap (`pageSize ≤ 100`, `currentPage ≤ 50`, ≤ 5 filters, depth ≤ 5), `Store` header usage + **runtime discovery store codes** (không hardcode), canonical URL rule = `storeConfig.base_url + url_rewrites[entry khớp url_key+url_suffix]` (§3.5b), KHÔNG dùng `base_url+url_key` concatenation làm canonical.
- **Guardrails ops** (§12) — điều kiện tiên quyết công bố.
- **Thiết kế không lock-in proprietary**: contract curated phải giữ field semantics ánh xạ 1-1 sang UCP `catalog.search`/`catalog.lookup` concepts (sku, name, price+currency, availability, canonical url, category) để adapter D sau này là mapping, không phải refactor.

## 8. Data contract (khái niệm — field subset cho agent)

Product: `sku`, `name`, `url_key`, `url_suffix`, **`url_rewrites[{url}]` (nguồn canonical — xem dưới)**, `stock_status`, `price.regularPrice.amount{value,currency}`, `special_price`, `minimal_price`, `image{url}`, `short_description` (chỉ nếu merchant muốn), `categories{id,name,url_key,url_suffix}`, `__typename`; configurable: inline fragment `... on ConfigurableProduct { options, variants }` (chỉ public attributes).

Store: `storeConfig{ store_code, locale, base_currency_code, default_display_currency_code, base_url, product_url_suffix, category_url_suffix }`. Categories: `categoryList{id,name,url_key,url_suffix,children}` (tránh `url_path` — deprecated).

**Canonical URL rule (v2 — thay naive concatenation):**
1. Nguồn chuẩn = **`url_rewrites` của store view đang query** (mỗi store một tập riêng — verified §3.5b). Entry khớp `url_key + url_suffix` = canonical hiện hành; các entry khác = alias/history (agent không dùng làm canonical).
2. Public URL đầy đủ = `storeConfig.base_url` (cùng request, cùng store) + rewrite entry đó.
3. `canonical_url` field: dùng KHI non-null (ưu tiên cao nhất — biểu thị custom canonical); trong install hiện tại luôn null (§3.5b).
4. `url_rewrites` rỗng với store X → product KHÔNG công khai ở store X — agent phải bỏ qua, KHÔNG tự ghép URL.
5. **Documented limitation**: nếu merchant cấu hình Mirasvit **conditional canonical rewrite** (regex), canonical storefront có thể khác `url_rewrites` — GraphQL không thể tái lập (LC-30 §0.A đã chứng minh request-bound). Contract ghi rõ giới hạn này; KHÔNG tái implement Mirasvit trong agent contract.
6. KHÔNG BAO GIỜ dùng `base_url + url_key(+suffix)` như authoritative canonical — chỉ là fallback hiển thị khi rewrite data thiếu, và phải đánh dấu "unverified".

**KHÔNG expose**: `only_x_left_in_stock` (kể cả khi config bật — quyết định giữ của v1: exact/partial quantity không công khai; expose availability/status thôi), tier price của group khác guest, cost/supplier/admin-only attributes, unpublished/disabled products (core đã lọc theo store visibility + status), reviews chứa PII (OUT of contract).

**Quyết định noindex (giữ v1 + exceptions rõ ràng)**: noindex (Mirasvit) là **search-engine indexing policy**, KHÔNG tự động làm entity catalog công khai biến mất khỏi machine API — lý do: noindex thường gắn filter/duplicate-URL contexts. **Exceptions LUÔN exclude (bắt buộc)**: product/category **disabled**, **non-visible individually**, **non-public** (status/visibility không công khai), **store-unassigned** (không thuộc store view đang query — `url_rewrites: []` là tín hiệu, §3.5b). Core GraphQL + Elasticsuite đã enforce các exclusion này theo store scope.

## 9. Multi-store behavior

Agent chọn store bằng `Store: <code>` header (verified). Mặc định không header = default store. Giá/locale/tên/URL theo store view. Luận La Launchpad hiện tại: store-code URL (LA song song `task/en-store-404-investigation`) không ảnh hưởng GraphQL (GraphQL không đi qua store-code path prefix). LC-30 `/llms.txt` per-store đã có sẵn làm discovery map locale↔store.

## 10. Security / privacy

Bối cảnh: SECURITY_BASELINE level HIGH (payment + PII). Nguyên tắc: **phân biệt technical exposure (endpoint đã mở — không phải decision của LA-22) với discovery/exploitation exposure (công bố làm contract chính thức → tăng discoverability/automation/traffic kỳ vọng lên cả mutation surface)**. V1 đã sai khi coi "đã mở thì công bố không tăng gì" — hiệu chỉnh v2:

**Raw `/graphql` có an toàn để quảng bá cho agent bất kỳ HÔM NAY? — NO.** Lý do (từ audit chính nó): introspection mở; anonymous guest-cart/customer-auth mutations tồn tại; third-party fields rủi ro (`mpSmtpBestsellers`); không rate limiter riêng; GraphQL POST thực thi query arbitrary shape.

Trả lời bắt buộc:
- **Minimum prerequisite để chấp nhận**: checklist §12 đạt (rate limit nginx/WAF/Cloudflare trên `/graphql`; depth/complexity bật + verify; quyết định introspection; `mpSmtpBestsellers` xử lý; mutation-blocking heuristic hoặc facade C duyệt).
- **Mutation có loại được kỹ thuật cho agent surface?** KHÔNG ở mức Magento core cho anonymous per-field. Chỉ (a) WAF heuristic chặn POST chứa `mutation` signature (best-effort, bypass được bằng whitespace/alias tricks) hoặc (b) facade C (airtight). → Đây chính là lý do hard rule §7 tồn tại và C là fallback.
- **Query shape/pageSize/depth bound?** Depth: core GraphQL query-depth config (check khi implement). pageSize/currentPage: KHÔNG ép server-side cho anonymous core GraphQL (pageSize cap 100 có sẵn phần products? — verify khi implement; nếu không, bound bằng contract + WAF). Complexity: không có sẵn → WAF/ops.
- **GraphQL GET caching**: core hỗ trợ GET query — canned read queries nên GET để tận dụng CDN/nginx cache; POST arbitrary KHÔNG cache và tốn resources → contract bắt buộc GET cho canned queries.
- **Operational impact của arbitrary POST**: mỗi query là code-execution-shaped workload (resolver fan-out tùy depth, search engine hit, DB join); không rate limit + không complexity cap = DoS/abuse vector cho catalog data scraping công khai.
- **Phân trách nhiệm**: Magento = resolver cache, depth config, ACL (đã có); Nginx/WAF/Cloudflare = rate limit, GET cache, mutation-signature heuristic, bot control; Custom code (chỉ nếu B thất bại) = facade C enforce allowlist. Không mong Magento core làm được phần WAF.

Các finding khác giữ nguyên từ v1:
1. `mpSmtpBestsellers` (Mageplaza_Smtp): khuyến nghị disable/cấu hình (TL decision, follow-up riêng).
2. Mageplaza OSC anonymous webapi (`isEmailAvailable` — email enumeration, guest-cart mutations): KNOWN_RISKS, TL quyết remediation (OSC guest checkout cần các route này).
3. Introspection: quyết định để mở (contract curated cần schema knowledge cho agent; schema không chứa secret) — NHƯNG chỉ chấp nhận khi rate limit đã hoạt động; re-open nếu WAF không đứng trước được.
4. Không PII trong contract; access log không ghi query body đầy đủ (chỉ operation name + size).

## 11. Performance / cache

- `GraphQlCache` + `GraphQlResolverCache` enabled (resolver cache theo tag product/category).
- Agent contract ép `pageSize ≤ 100`, `currentPage ≤ 50`, ≤ 5 filter fields, depth ≤ 5 (tài liệu + guardrail).
- Query nặng bất kỳ (arbitrary GraphQL): guard bằng nginx `limit_req` zone trên `/graphql` + (option) Cloudflare rate rule — không expose GraphQL *không có* rate limit như hiện trạng.
- HTTP caching toàn page cho GraphQL chỉ khi `X-Magento-Cache-Id`/context ổn định — với anonymous agent KHÔNG dùng cache-id (no session dependency); dựa resolver cache + CDN cache cho các canned query nếu sau này cần.

## 12. Guardrails checklist (ĐIỀU KIỆN TIÊN QUYẾT — hard rule §7: chưa đạt thì KHÔNG công bố Layer-2 trong `/llms.txt`)

1. **Rate limiting** trên `/graphql` hoạt động ở nginx (`limit_req`) và/hoặc Cloudflare/WAF — verify bằng probe trả 429.
2. **Depth/complexity**: kiểm chứng config query-depth của core GraphQL trên 2.4.8 (verify khi implement); set depth ≤ 10. Complexity cap không có sẵn core → tài liệu trong contract + WAF.
3. **Mutation-blocking heuristic** (WAF pattern chặn POST chứa mutation signature cho anonymous /graphql) — được TL chấp nhận là best-effort, HOẶC quyết định chuyển facade C (§6) cho public surface.
4. **GET-only canned queries**: contract bắt buộc GET (cache được ở nginx/CDN); POST không nằm trong contract công khai.
5. **`mpSmtpBestsellers`** disable/cấu hình (TL decision).
6. **Introspection decision** ghi nhận: giữ mở, chỉ khi mục 1 đã hoạt động; re-open nếu không đứng được WAF.
7. Đảm bảo `catalog_product`/search indexes valid (audit đã gặp index thiếu → agent query fail).
8. Theo dõi: log 429/5xx trên `/graphql`; alert khi throughput bất thường.

## 13. LC-30 integration (tương lai — KHÔNG sửa trong task này)

Sau khi contract ổn định **VÀ guardrails §12 demonstrably in place** (hard rule §7), LC-30 `/llms.txt` thêm **một section ngắn duy nhất** (không API manual):

```
## Machine-readable commerce

- Agent contract: {base_url}ai/commerce-contract.md (curated read queries, Store codes discovered via storeConfig)
```

Discovery khác: `/.well-known/ucp` = cơ chế discovery tương lai của UCP (khi adopt theo §14 — KHÔNG triển khai giờ, chỉ giữ kiến trúc không cản trở); HTTP `Link` header (thừa nếu đã có llms.txt section). **Không quảng bá raw `/graphql` trực tiếp.**

## 14. UCP / MCP decision (v2 — hiệu chỉnh theo evidence 2026-04-08)

Bối cảnh UCP (tính đến 2026-08-24): **UCP stable release 2026-04-08** với formal governance; Google + Shopify là permanent Governing Council members; **Stripe đã join Governing Council**; technical participation gồm Amazon, Meta, Microsoft, Salesforce... UCP định nghĩa `/.well-known/ucp` discovery và **catalog capabilities: `catalog.search`, `catalog.lookup`**; **MCP bindings tồn tại cho catalog search/lookup**. → Nhận định "UCP chưa mature" của v1 là **SAI** và đã bỏ.

Trả lời tường minh 5 câu hỏi:

1. **UCP có chiến lược relevant? — YES.** Bằng chứng: stable release 2026-04-08 + governance council (Google/Shopify/Stripe) + participation của các major platform + catalog capability spec + MCP bindings. UCP là interop layer đang được chuẩn hóa chính thống cho đúng use case của LA-22.
2. **Launchpad có nên implement UCP catalog NOW? — NO.** Bằng chứng: (a) Magento GraphQL đã thỏa toàn bộ use case Layer-2 anonymous read hôm nay (§3.1, §4) — không có gap functional; (b) chưa có yêu cầu transaction/partner thật nào ép buộc UCP; (c) implement UCP bây giờ = adapter/protocol work (well-known endpoint, capability mapping, versioning theo spec) mà không có consumer bắt buộc. Deferral vì **need**, KHÔNG vì maturity.
3. **Trigger adopt UCP**: (i) partner/platform yêu cầu UCP interop (vd agent marketplace, Google/Shopify-ecosystem integration); (ii) Launchpad cần agent **transaction** (cart/checkout via protocol có governance) — khi đó UCP là khung đúng thay vì tự chế; (iii) MCP-based agent traffic đủ lớn để cần chuẩn hóa tool contract.
4. **Magento GraphQL làm internal source sau adapter UCP? — YES.** Kiến trúc §7 thiết kế đúng cho việc này: UCP adapter gọi read contract (curated queries/facade), không đụng data path khác.
5. **Adapter UCP tương lai có tránh sửa core data path? — YES, theo thiết kế**: adapter là layer mỏng map `catalog.search`→products query (search/filter/price/availability) và `catalog.lookup`→lookup by SKU/URL (url_rewrites canonical). Ràng buộc đặt lên contract hôm nay (§7): field semantics phải giữ ánh xạ 1-1 sang UCP concepts để adapter = mapping, không phải refactor; **tránh tạo proprietary contract cản trở UCP sau này**.

Verdict: **UCP = LATER (preferred future interoperability layer, không phải "immature") · MCP = LATER (via UCP MCP bindings khi có trigger)**. Chiến lược: stable read contract trước, protocol adapter sau.

## 15. File/module impact plan (khi implement — KHÔNG làm bây giờ)

| Component | Vị trí dự kiến | Loại |
|---|---|---|
| Agent Contract document | `pub/media/.../` hoặc static CMS/route tĩnh (TBD ở implementation spec con) — KHÔNG phải module PHP | Doc |
| Guardrail nginx (limit_req, GET cache, mutation heuristic) | `compose/nginx/default.conf` + vhost demo | Infra config |
| GraphQL depth config | core config (`graphql query depth`) | Magento config |
| LC-30 section | `Secomm_AiDiscoverability` (source/section mới, bounded) | Code (nhỏ, riêng task sau) |
| Remediation mpSmtpBestsellers/OSC | riêng ticket security | Code/config |
| **(Contingency)** Facade C module `Secomm_AiCommerceRead` | `app/code/Secomm/...` — CHỈ khi B không đạt guardrail sau 1 chu kỳ (§7) | Code (bounded, delegate sang GraphQL/services nội bộ) |

**Không module PHP mới cho Layer-2 data path ở giai đoạn NOW (Option B).**

## 16. Acceptance criteria (cho spec này — architecture decision)

- AC-001: Mỗi UC-01..UC-08 có mapping tới GraphQL query cụ thể đã verify live (§3.1, §4) ✓
- AC-002: Decision matrix A–E đầy đủ tiêu chí + verdict NOW/LATER/REJECT (§6) ✓
- AC-003: Guardrail checklist (§12) là điều kiện tiên quyết có hard rule không quảng bá raw `/graphql` (§7, §10) ✓
- AC-004: Security findings hiện hữu + phân biệt technical vs discovery/exploitation exposure (§3.6, §10) ✓
- AC-005: UCP/MCP decision dựa evidence 2026-04-08, trả lời tường minh 5 câu hỏi (§14) ✓
- AC-006: Không code production nào được tạo trong task này ✓
- AC-007: Canonical URL contract từ nguồn proven (`url_rewrites`/`canonical_url`) + documented limitation Mirasvit, KHÔNG concatenate (§3.5b, §8) ✓
- AC-008: Store-code evidence labeled LOCAL, contract runtime-discovery (§3.5) ✓

## 17. Test strategy (khi implement guardrails)

- Smoke: mỗi canned query của contract → 200 + field subset đúng + `Store` header switching đúng store.
- Negative: pageSize > cap → guardrail từ chối/HTTP 429 (limit_req); mutation fields ngoài contract không nằm trong canned docs (không enforceable per-field ở core — document rõ giới hạn).
- Multi-store: cùng query 2 store codes → khác price/locale/url.
- Regression: storefront GraphQL hiện hữu không đổi.

## 18. Risks / open questions

1. **Anonymous GraphQL mutations + brute-force token** đã mở từ trước (không do LA-22) — cần TL quyết remediation level (WAF rules? Mageplaza review?). OPEN.
2. Introspection mở — trade-off chấp nhận có điều kiện rate-limit (§10.3, §12.6); re-open nếu WAF không đứng trước được. OPEN-ACCEPTED (conditional).
3. `mpSmtpBestsellers` secret-in-args — TL decide disable. OPEN.
4. Noindex≠API-invisibility decision (§8, kèm exceptions bắt buộc) — cần TL confirm. OPEN.
5. Elasticsuite index operational dependency (agent query fail nếu index stale) — monitoring cần thiết. OPEN.
6. Contract enforcement là documentation-based + WAF heuristic (không technical per-field gate trên core GraphQL) — nếu cần enforce airtight → chuyển facade C (§6, §7 contingency). **OPEN — security choice chưa chốt dứt điểm: mutation-blocking best-effort (B) hay facade (C) làm public surface.**
7. Mutation-blocking heuristic bypass-able (whitespace/aliasing) — chấp nhận được chỉ trongcombination với rate limit + GET-only contract; đánh giá lại định kỳ. OPEN.
8. UCP adoption trigger (§14.3) — theo dõi partner/transaction requirement. OPEN.

## 19. Explicit implementation boundaries

Task này: **chỉ spec/audit**. Không REST/GraphQL endpoint mới, không MCP/UCP, không sửa LC-30, không sửa module nào. Implementation (guardrails + contract doc + LC-30 section) = ticket(s) con riêng theo spec-first rule, mỗi ticket reference spec này.

---

## Evidence (audit 2026-08-24; v2 bổ sung canonical probes cùng ngày)

Live probes: §3.1 (curl anonymous `/graphql`: storeConfig/products/categoryList/Store header/introspection); §3.2 (REST 401); §3.3 (JSON-LD); §3.5b (canonical probes: introspection ProductInterface/CategoryInterface fields; simple `throw-mohair`, bundle `bundle-new-home-gift`, configurable `bedding-linen`, 7 categories; store-view probe `Store: us_en` → `url_rewrites: []`; storefront `<link rel=canonical>` so khớp). Module states: `app/etc/config.php`. Custom effects: toàn bộ app/code sweep (webapi.xml ×15, schema.graphqls ×5, di.xml plugin/preference sweep; findings chính §3.6, §10). UCP evidence: stable release 2026-04-08, governance (Google/Shopify permanent; Stripe joined; Amazon/Meta/Microsoft/Salesforce participation), `/.well-known/ucp`, `catalog.search`/`catalog.lookup`, MCP bindings — theo TL review input 2026-08-24.
