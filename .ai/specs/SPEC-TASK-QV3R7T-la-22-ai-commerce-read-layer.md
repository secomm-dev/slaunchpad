# [SLP][TASK-QV3R7T] LA-22 — AI Commerce Read Layer (Audit / Architecture Decision)

Specification ID: SPEC-TASK-QV3R7T

> **External ref**: LA-22 · **Mode**: A (spec-first) · **Status**: DRAFT — chờ TL review. **Không có production code.** Audit + architecture decision only.
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

GraphQL resolution order: `Store` header → store cookie → default. Store header đủ để agent chọn `default` / `us_en` / ...; giá, locale, category tree, name, url_key theo store view. ĐÃ VERIFY live (header `Store: us_en`).

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

## 6. Architecture options

### OPTION A — Reuse Magento GraphQL trực tiếp (curated contract, không code mới cho data path)

- **Capability**: đầy đủ UC-01→UC-08 (verified §3.1). Typed schema, self-documenting (introspection), multi-store qua `Store` header, FPC-ish caching qua `GraphQlCache`/`GraphQlResolverCache` (resolver-level).
- **Cost**: ~0 code; chi phí là documentation + guardrails ops.
- **Security surface**: (a) endpoint này ĐÃ mở anonymous ngay từ nay (không phải quyết định mới của LA-22) — expose thêm curated contract không mở thêm mặt nào; (b) risks tồn tại độc lập: introspection mở, mutation fields (guest cart mutations!) có sẵn anonymous, `mpSmtpBestsellers`, không rate limit — LA-22 phải kèm guardrail plan (§12).
- **Maintenance**: theo Magento upgrade (schema ổn định theo semver của M2.4); không có code Secomm phải fix security.
- **Agent usability**: cao (GraphQL là contract agent-friendly, có sẵn schema).
- **Duplication risk**: 0.
- **Nhược**: contract "gồm những field nào an toàn" là quy ước tài liệu, không ép buộc kỹ thuật — cần guardrails ops đi kèm.

### OPTION B — Reuse Magento REST

- 401 anonymous (§3.2). Muốn dùng phải phát hành Integration token → secret distribution cho "public AI agents" là vô lý về security. **LOẠI** cho anonymous read.

### OPTION C — Secomm agent-safe read facade (`/ai/catalog/*` REST hoặc GraphQL module mới)

- **Capability**: kiểm soát field-by-field, schema đóng, dễ cache HTTP, dễ rate limit per-route.
- **Cost**: module mới (controller/resolver + ACL anonymous + tests + PHPCS + bảo trì dài hạn) — **duplicate lại những gì GraphQL đã làm**.
- **Justification chỉ xảy ra nếu**: (1) cần che giấu schema hoàn toàn, (2) cần SLA/rate-limit per-contract mà Magento GraphQL không đáp ứng, (3) cần format phi-GraphQL cho agent đơn giản. Chưa có bằng chứng nào trong audit đòi hỏi điều này.
- **Risk**: mở thêm attack surface mới (code Secomm = thêm code phải audit security), tách khỏi search engine behavior nếu tự query repository.

### OPTION D — MCP adapter (phase sau)

MCP server wrap queries (A hoặc C). Giá trị thật khi có transaction; read-only catalog qua MCP = transport khác của cùng data. **DEFER**.

### OPTION E — UCP discovery

UCP (đang chuẩn hóa commerce protocol) chưa mature, chưa có consumer agent phổ biến trên Magento stack. **DEFER**; thiết kế §13 để thêm sau mà không break.

## 7. Recommended architecture

**OPTION A — curated anonymous GraphQL contract + guardrails vận hành. KHÔNG module mới cho data path.**

Lý do:
1. Bằng chứng live: mọi UC đều thỏa bằng core GraphQL hôm nay (§3.1, §4).
2. Endpoint anonymous đã tồn tại — "expose" = công bố contract dùng field subset, không tăng attack surface (surface tăng chỉ nếu bật thêm module GraphQL).
3. Reuse > duplication theo decision standard của task; Option C không có justification chứng minh được.
4. Multi-store, pricing theo store, search đồng nhất storefront, cache resolver — miễn phí.

Component đi kèm (bắt buộc để A an toàn — đây là "smallest safe", không phải "zero work"):
- **Agent Contract document** (file tĩnh, versioned — xem §15): tập query/file xác định + pagination cap (`pageSize ≤ 100`), bộ field cho phép, cách dùng `Store` header, canonical URL rule (`{base_url}{url_key}{url_suffix}`).
- **Guardrails ops** (§12): query depth/complexity, rate limiting layer, tắt/chặn các surface rủi ro §3.6.
- Sau này nếu contract cần enforce kỹ thuật → mới cân nhắc C như "schema gateway" (điểm re-evaluate: §18).

## 8. Data contract (khái niệm — field subset cho agent)

Product: `sku`, `name`, `url_key` (+ suy ra canonical URL), `stock_status`, `price.regularPrice.amount{value,currency}`, `special_price`, `minimal_price`, `image{url}`, `short_description` (chỉ nếu merchant muốn), `categories{id,name,url_path}`, `__typename` product type; configurable: `variants` (chỉ public attributes) / `configurable_options`.

Store: `storeConfig{ store_code, locale, base_currency_code, default_display_currency_code, base_url }`. Categories: `categoryList{id,name,url_path,children}`.

**KHÔNG expose**: `only_x_left_in_stock` (kể cả khi config bật — quyết định: exact/partial quantity không công khai), tier price của group khác guest, cost/supplier/admin-only attributes, unpublished/disabled products (core đã lọc theo store visibility + status — verified qua Elasticsuite index chỉ chứa public entities), reviews chứa PII (OUT of contract).

**Quyết định noindex**: noindex (Mirasvit) là **search-engine indexing policy**, KHÔNG áp cho API visibility trong Layer-2 — lý do: noindex thường gắn filter/duplicate-URL contexts, còn GraphQL trả entity theo store visibility; áp noindex vào API sẽ khiến agent thiếu product công khai. Ghi nhận là quyết định có thể re-open (§18). Ngoại lệ dự kiến: product `visibility=Not Visible Individually` — core đã loại khỏi search result.

## 9. Multi-store behavior

Agent chọn store bằng `Store: <code>` header (verified). Mặc định không header = default store. Giá/locale/tên/URL theo store view. Luận La Launchpad hiện tại: store-code URL (LA song song `task/en-store-404-investigation`) không ảnh hưởng GraphQL (GraphQL không đi qua store-code path prefix). LC-30 `/llms.txt` per-store đã có sẵn làm discovery map locale↔store.

## 10. Security / privacy

Bối cảnh: SECURITY_BASELINE level HIGH (payment + PII). Nguyên tắc: **Layer-2 không thêm surface; và phải đóng bớt surface rủi ro hiện có**.

1. Anonymous mutations trên `/graphql` (guest cart, `generateCustomerToken` brute-force...): ngoài agent contract; cần ops guardrail (§12.4) — không chặn được hoàn toàn trong Magento core, xử lý ở WAF/Cloudflare/nginx.
2. `mpSmtpBestsellers` (Mageplaza_Smtp): khuyến nghị disable module hoặc cấu hình để query không khả dụng anonymous (follow-up riêng, không thuộc LA-22 code).
3. Mageplaza OSC anonymous webapi (`isEmailAvailable` — email enumeration, guest-cart mutations): ghi nhận KNOWN_RISKS; remediation là decision của TL (cosmetic concern: có thể cần giữ cho OSC guest checkout hoạt động).
4. Introspection: giữ mở cho agent usability (đây là public catalog contract), chấp nhận rủi ro schema-knowledge — schema không chứa secret; guardrail bằng rate limit + depth/complexity.
5. Không PII trong contract; log không ghi query body đầy đủ (chỉ operation name + size) — áp cho access log nginx.
6. Rate limiting + query complexity: bắt buộc trước khi công bố contract (§12).

## 11. Performance / cache

- `GraphQlCache` + `GraphQlResolverCache` enabled (resolver cache theo tag product/category).
- Agent contract ép `pageSize ≤ 100`, `currentPage ≤ 50`, ≤ 5 filter fields, depth ≤ 5 (tài liệu + guardrail).
- Query nặng bất kỳ (arbitrary GraphQL): guard bằng nginx `limit_req` zone trên `/graphql` + (option) Cloudflare rate rule — không expose GraphQL *không có* rate limit như hiện trạng.
- HTTP caching toàn page cho GraphQL chỉ khi `X-Magento-Cache-Id`/context ổn định — với anonymous agent KHÔNG dùng cache-id (no session dependency); dựa resolver cache + CDN cache cho các canned query nếu sau này cần.

## 12. Guardrails checklist (điều kiện trước khi công bố Layer-2)

1. `bin/magento config:set web/graphql/...` — kiểm chứng depth/complexity config khả dụng trên 2.4.8 (query depth limiter có sẵn core GraphQL); set depth ≤ 10.
2. nginx `limit_req` trên `/graphql` (local + demo vhost).
3. Disable/chặn `mpSmtpBestsellers` anonymous (TL decision).
4. Đảm bảo `catalog_product`/search indexes valid (dependency vận hành — audit đã gặp index thiếu).
5. Theo dõi: log 429/5xx trên /graphql.

## 13. LC-30 integration (tương lai — KHÔNG sửa trong task này)

Sau khi contract ổn định (≥1 chu kỳ vận hành), LC-30 `/llms.txt` thêm **một section ngắn duy nhất** (không API manual):

```
## Machine-readable commerce

- GraphQL: {base_url}graphql (Store header: <codes>) — agent contract: /ai/commerce-contract.md
```

Discovery khác (đánh giá, không khuyến nghị giờ): `/.well-known/` (chuẩn hóa sau cùng với UCP), HTTP `Link` header (thừa nếu đã có llms.txt section). **UCP/MCP discovery: DEFER — xem §14.**

## 14. UCP / MCP decision

| Câu hỏi | Trả lời |
|---|---|
| UCP mature cho Launchpad bây giờ? | **NO/LATER** — spec đang tiến hóa, chưa có consumer phổ biến trên Magento; adopt sớm = rủi ro rework. |
| MCP hữu ích cho anonymous read catalog? | Giá trị nhỏ: chỉ là transport khác; hữu ích thật khi có tool composition + transaction. |
| MCP không có transaction có giá trị? | Có một phần (tool discovery cho agent), nhưng không đáng chi phí bảo trì server riêng lúc này. |
| Chiến lược đúng? | **Đúng — establish stable read contract (A) trước, thêm protocol adapter (MCP/UCP) sau** như layer mỏng trên contract đã ổn định. Không lock-in: GraphQL contract không phụ biệt transport. |

Verdict: **MCP = LATER · UCP = LATER** (re-evaluate khi có business case thật: agent purchase, multi-agent tooling).

## 15. File/module impact plan (khi implement — KHÔNG làm bây giờ)

| Component | Vị trí dự kiến | Loại |
|---|---|---|
| Agent Contract document | `pub/media/.../` hoặc static CMS/route tĩnh (TBD ở implementation spec con) — KHÔNG phải module PHP | Doc |
| Guardrail nginx | `compose/nginx/default.conf` + vhost demo | Infra config |
| GraphQL depth config | core config (`graphql query depth`) | Magento config |
| LC-30 section | `Secomm_AiDiscoverability` (source/section mới, bounded) | Code (nhỏ, riêng task sau) |
| Remediation mpSmtpBestsellers/OSC | riêng ticket security | Code/config |

**Không module PHP mới cho Layer-2 data path.**

## 16. Acceptance criteria (cho spec này — architecture decision)

- AC-001: Mỗi UC-01..UC-08 có mapping tới GraphQL query cụ thể đã verify live (§3.1, §4) ✓
- AC-002: Recommendation chứng minh được bằng audit tại sao B/C không cần thiết (§6) ✓
- AC-003: Guardrail checklist (§12) định nghĩa trước khi công bố ✓
- AC-004: Security findings hiện hữu được ghi nhận trung thực (§3.6, §10) ✓
- AC-005: MCP/UCP decision có rationale (§14) ✓
- AC-006: Không code production nào được tạo trong task này ✓

## 17. Test strategy (khi implement guardrails)

- Smoke: mỗi canned query của contract → 200 + field subset đúng + `Store` header switching đúng store.
- Negative: pageSize > cap → guardrail từ chối/HTTP 429 (limit_req); mutation fields ngoài contract không nằm trong canned docs (không enforceable per-field ở core — document rõ giới hạn).
- Multi-store: cùng query 2 store codes → khác price/locale/url.
- Regression: storefront GraphQL hiện hữu không đổi.

## 18. Risks / open questions

1. **Anonymous GraphQL mutations + brute-force token** đã mở từ trước (không do LA-22) — cần TL quyết remediation level (WAF rules? Mageplaza review?). OPEN.
2. Introspection mở — trade-off chấp nhận (§10.4); re-open nếu có finding ngược. OPEN-ACCEPTED.
3. `mpSmtpBestsellers` secret-in-args — TL decide disable. OPEN.
4. Noindex≠API-invisibility decision (§8) — cần TL confirm. OPEN.
5. Elasticsuite index operational dependency (agent query fail nếu index stale) — monitoring cần thiết. OPEN.
6. Contract enforcement là documentation-based (không technical per-field gate) — nếu cần enforce → re-evaluate Option C. OPEN.

## 19. Explicit implementation boundaries

Task này: **chỉ spec/audit**. Không REST/GraphQL endpoint mới, không MCP/UCP, không sửa LC-30, không sửa module nào. Implementation (guardrails + contract doc + LC-30 section) = ticket(s) con riêng theo spec-first rule, mỗi ticket reference spec này.

---

## Evidence (audit 2026-08-24)

Live probes: xem §3.1 bảng (curl anonymous `/graphql`). REST 401: §3.2. JSON-LD: §3.3. Module states: `app/etc/config.php`. Custom effects: toàn bộ app/code sweep (webapi.xml ×15, schema.graphqls ×5, di.xml plugin/preference sweep — chi tiết trong working notes của audit session; findings chính tóm tắt §3.6, §10).
