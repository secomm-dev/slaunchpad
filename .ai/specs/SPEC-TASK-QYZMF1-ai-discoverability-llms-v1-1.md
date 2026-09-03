Specification ID: SPEC-TASK-QYZMF1

# SPEC-TASK-QYZMF1 — AI Discoverability llms.txt V1.1 (richer business context + agent guidance)

- Classification: MINI (Mode C) — bounded output enrichment of an existing deterministic generator; no new config fields, no endpoint behavior change, no data-contract change
- Module: `Secomm_AiDiscoverability` (related read-only seam: `Secomm_AiCommerce`)
- Tuân thủ `config/constitution.md` + `config/checklist.md` (magento-spec team standards SSOT)

## 1. Mục tiêu

Nâng cấp llms.txt V1.0 → V1.1: giàu ngữ cảnh kinh doanh + hướng dẫn agent, vẫn deterministic, bounded, factual, store-view aware, config-driven. KHÔNG chế text marketing, KHÔNG claim capability giao dịch chưa tồn tại (AiCommerce hiện READ-ONLY).

## 2. Magento core evidence (đã kiểm chứng repo này + runtime DB)

1. **Category `meta_description` / `description` là EAV store-scope**: `catalog_eav_attribute.is_global = 0` cho cả 2 attribute (query runtime) → `CategoryRepositoryInterface::get($id, $storeId)` (đang dùng trong `CategoriesSource`) trả giá trị store-scoped — không cần flow mới, không N+1.
2. **CMS `meta_description`**: cột bảng `cms_page.meta_description` — giá trị per-page (KHÔNG per-store-view); store binding qua bảng `page_store` (`PageInterface::getStores()`), đã được `CmsPagesSource::isVisibleInStore()` kiểm tra. Không parse Page Builder content.
3. **Sitemap**: `Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory` (đang dùng trong `SitemapRefsSource`) — đọc file sitemap đã tồn tại theo store, không generate, không hardcode `/sitemap.xml`.
4. **Safe plain-text**: core `Magento\Framework\Filter\StripTags` (strip_tags + html-entities guard) và `Magento\Framework\Filter\RemoveTags`; formatter hiện tại đã dùng `strip_tags` + control-char + whitespace collapse + Markdown-bracket escape (`LlmsTxtFormatter::sanitizeText`) — tái dùng, không thêm dependency.
5. **Store-aware reads**: `CategoryRepositoryInterface::get($id, $storeId)` (EAV store scope), `PageRepositoryInterface::getById` + `getStores()` — đúng pattern hiện tại.
6. **Cache identity**: nội dung mới chỉ đọc từ (a) config đã có (`brand_summary`, site title, AiCommerce flag — observer `ConfigInvalidation`/`AiCommerceConfigInvalidation` đã phá cache), (b) category attributes đọc trong cùng repository `get()` đã có (`CategoryInvalidation` trên `catalog_category_save_after` đã phá cache), (c) CMS meta đã có (`CmsInvalidation`). KHÔNG nguồn mới → không thay đổi invalidation, không thêm cron.

## 3. Approach

### 3.1 Section structure V1.1 (thứ tự cố định)

```
# <Site Title>
> <brand_summary | fallback như V1.0>
Locale / Currency
## Store Summary            (prose; chỉ khi brand_summary configured)
## Agent Guidance           (module-generated, chỉ khi commerce surface khả dụng)
## Priority Pages
## Featured Collections     (đổi tên từ "Collections"; description theo §3.3)
## Key Pages                (đổi tên từ "Pages")
## Machine-readable Commerce (endpoint + Purpose; giữ soft seam)
## Commerce Limitations     (chỉ khi commerce surface khả dụng)
## Sitemap
```

### 3.2 Prose sections (Store Summary / Agent Guidance / Commerce Limitations)

- Store Summary: nội dung = `general/brand_summary` đã configure (sanitize, bound 500 như summary). Blank → omit section (không fabricate).
- Agent Guidance + Commerce Limitations: text tĩnh deterministic sinh bởi module, mô tả đúng năng lực thực tế — /ai/* read-only, prefer structured endpoint over scraping, không cart/checkout/order/account/payment/address, respect rate limits + cache (KHÔNG nêu con số rate limit). Cả hai chỉ render khi `CommerceEndpointsSource` trả entries ≠ [] (AiCommerce present + enabled) — khi disabled, KHÔNG § nào ngụ ý /ai tồn tại.
- KHÔNG đề cập UCP / /.well-known/ucp / MCP / WebMCP / create_cart / checkout mutation / Shop Pay / order tracking — audit test sẽ chặn.

### 3.3 Category/CMS description precedence

- Category: (1) `meta_description` nếu usable sau sanitize; (2) `description` attribute (HTML) → sanitize plain-text, bound 240 chars; (3) omit. Bound link description nâng 200 → 240. Tất cả đọc từ category object đã load store-scoped trong `CategoriesSource` (0 query thêm).
- CMS: giữ nguyên V1.0 — `meta_description` nếu có, không có thì link-only (không fallback content/heading — tránh parse Page Builder).
- Formatter sanitize giữ nguyên (strip HTML/control/Markdown-break) — không bao giờ emit raw HTML.

### 3.4 Machine-readable Commerce presentation

- Entries mở rộng `purpose` text; render dạng `### <Label>` + `GET <url>` + `Purpose: <text>` (Product Detail vẫn URL template `{sku}`, KHÔNG Markdown link).
- Không đổi endpoint behavior/soft seam (`ModuleList` + config flag).

### 3.5 URL/global bounds

- Toàn bộ URL-carrying sections (kể cả commerce detail entries) vẫn qua collector dedup + global `max_urls` bound như V1.0. Prose sections không mang URL riêng → không tính vào bound.

## 4. Data contract / compatibility

- Không field config mới. Giá trị config, cache identity, endpoint router, ETag/304 semantics không đổi. Output text thay đổi (đổi tên section, thêm section) — version CHANGELOG 1.4.0.

## 5. Acceptance criteria

1. Brand summary render ở blockquote và (khi configured) Store Summary; blank → omit, không fabricate.
2. Agent Guidance deterministic, chỉ khi commerce khả dụng; không có số rate limit cụ thể.
3. AiCommerce enabled → endpoints + Purpose render đủ 4; disabled → không có Machine-readable Commerce/Agent Guidance/Commerce Limitations.
4. Không string capability bị cấm (UCP, MCP, WebMCP, create_cart, …) trong output.
5. Featured Collections: meta_description → description fallback (plain-text, bound 240) → omit; HTML stripped.
6. Key Pages: meta_description nếu có, không dump content.
7. Canonical/eligibility/scope filtering và global URL bound giữ nguyên (regression tests cũ xanh).
8. Sitemap section giữ logic native hiện tại, label `XML Sitemap`.
9. Store-view isolation: nội dung store 2 không leak store 3 (runtime proof).

## 6. Out of scope

Transactional AI capability, UCP/WebMCP, JSON schema examples, new config fields, AiCommerce code changes, sitemap generation, cron regeneration.
