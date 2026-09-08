---
id: BUG-Q8L5RD
type: bug
title: 'AI catalog search returns page-1 items while echoing the requested page number for pages beyond the last page (page <= 50)'
project_code: SLP
parent: null
external_refs: {}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: null
risk: medium
status: open
created: '2026-09-08'
updated: '2026-09-08'
decisions: []
decision_assessment: non-material
decision_approval_summary:
  total: 0
  pending_approval: []
  approved: []
  rejected: []
  superseded: []
  last_synced: '2026-09-08'
verified_against_commit: null
components:
  - CMP-AICOMMERCE
source_areas:
  - app/code/Secomm/AiCommerce/Service/Catalog/SearchService.php
  - vendor/smile/elasticsuite/src/module-elasticsuite-catalog/Model/ResourceModel/Product/Fulltext/Collection.php
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: '2026-09-08'
supersedes: []
---

# [SLP][BUG-Q8L5RD] AI catalog search returns page-1 items while echoing the requested page number for pages beyond the last page (page <= 50)

## Overview & Bug Classification
- **Classification**: **BUG CONFIRMED** (live-proven, discovered during PHASE 8 llms.txt verification of TASK — không thuộc 3 defects của audit ban đầu)
- **Origin**: Phase 8 end-to-end llms.txt Example verification (combined-filter narrow result set)
- **Component**: `Secomm_AiCommerce` — Service/Catalog/SearchService (pass-through) + Smile Elasticsuite Fulltext collection runtime lifecycle
- **Severity**: Medium (chỉ ảnh hưởng request trang ngoài phạm vi thực tế; agent phân trang đúng contract không gặp; response metadata nói sai về cửa sổ dữ liệu đã phục vụ)

## Symptom & Reproduction Evidence
Local live stack (mirror của audit environment), 2026-09-08:

- `GET /ai/catalog/search?store=default&category=6&filter[color]=20` → `total_count: 2`
- `GET ...&page=2&page_size=20` → **HTTP 200**, `total_count: 2`, `"page": 2`, nhưng `items` = **2 sản phẩm của trang 1** (`sofa-meridian`, `sylvan-armchair`) — window trang 2 phải là rỗng.
- `GET /ai/catalog/search?store=default&q=Terra&page_size=1` với `page=1..6` → đúng thứ tự từng item; `page=7..11` → **luôn trả item đầu trang 1** dù `total_count: 6`.
- `page=99` → 400 `invalid_parameter` (parser cap `page <= 50` — đúng contract, trường hợp này ổn).

## Root Cause Analysis
`SearchService` chỉ pass-through (`setCurPage($page)`, `setPageSize($page_size)`, `load()`). Bằng chứng probe (script CLI trong container, **không có code AiCommerce nào trên đường chạy**):

1. **Engine nhận `from:0` dù curPage=7**: bật TRACE slowlog trên `magento2_default_catalog_product_*` rồi chạy lại flow — toàn bộ query engine log là `"from":0,"size":1`; không hề có query `from:6`. Offset pagination không bao giờ tới engine.
2. **Engine tự nhiên đúng**: gọi trực tiếp OpenSearch với cùng fulltext query + `"from":6,"size":1` → `total: 6, hits: []` (cửa sổ rỗng đúng).
3. **Chỉ class gốc bị, subclass thì không**: control experiment với `class Probe extends Smile\...\Fulltext\Collection` (cùng chuỗi call, `setCurPage(7)`) → `getCurPage()` giữ nguyên 7, engine request `from=6`, items rỗng — **đúng chuẩn**. Với instance class gốc qua `$om->create()` (Interceptor/di compiled) → sau `load()` `_curPage` bị đưa về 1, items = trang 1.
4. Không có call `setCurPage(1)` nào (probe override `setCurPage` không ghi nhận call nào với giá trị khác 7); `_filters` của Smile còn nguyên (total vẫn đúng 6) → là reset trạng thái phân trang trong lifecycle của instance class gốc (compiled Interceptor/di), không phải logic collection thuần.

Kết luận: hành vi nằm ở **lifecycle runtime của `Smile\...\Fulltext\Collection` gốc** (tầng vendor/di/interception), không phải do `SearchService` hay bất kỳ module Secomm nào. Response DTO chỉ echo lại `criteria['page']` (trang request) trong khi items là trang 1 — nên response tự mâu thuẫn.

Phạm vi: chỉ ảnh hưởng request `page` trong cap parser (1..50) nhưng vượt số trang thực (`ceil(total/page_size)`) — tức cửa sổ trống.

## Required Fix Contract (Acceptance Criteria for Implementation AI) — CHỜ TL DECIDE
1. Request trang ngoài phạm vi phải trả `items: []` với đúng `page` đã request (hoặc 400 theo nghĩa out-of-range — TL chọn), KHÔNG được trả nội dung trang khác.
2. Không đổi hành vi các trang trong phạm vi (đang known-good).
3. Phương án ứng viên (cho TL):
   - **A (module-level, smallest)**: sau `load()`, so `criteria['page']` với `$collection->getLastPageNumber()` (Smile đã override đúng theo total/page size) — nếu vượt thì trả `items: []` từ DTO (không query thêm).
   - **B (report upstream)**: issue cho Smile Elasticsuite kèm probe 1–3 (bằng chứng đã đầy đủ).
   - **C (không fix)**: nếu TL đánh giá agent-flow impact không đáng — nhưng cần ghi nhận `known_limitations` vì response hiện nói sai window.
4. Nếu fix: thêm regression test + live verify đúng quy trình (red trước, green sau).

## Notes
- llms.txt hiện không đưa ra claim nào về trang ngoài phạm vi → không có hướng dẫn sai nào cần thu hồi trong khi chờ quyết.
- Không ảnh hưởng 3 defects đã fix (BUG-7M4KQX / BUG-K8T3WR / BUG-V2N9DL — đều verify ở page mặc định) và không ảnh hưởng known-good pagination trong phạm vi trang.
