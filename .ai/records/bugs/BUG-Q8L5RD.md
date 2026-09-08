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
status: done
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
verified_against_commit: 6e91e0a7
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

## Required Fix Contract (Acceptance Criteria for Implementation AI) — RESOLVED (TL DECIDED: OPTION A)
1. Request trang ngoài phạm vi phải trả `items: []` với đúng `page` đã request (hoặc 400 theo nghĩa out-of-range — TL chọn), KHÔNG được trả nội dung trang khác.
2. Không đổi hành vi các trang trong phạm vi (đang known-good).
3. Phương án ứng viên (cho TL):
   - **A (module-level, smallest) — ✅ CHỌN (TL decision 2026-09-08)**: sau `load()`, derive lastPage từ `total_count` × requested `page_size` (không phụ thuộc trạng thái collection mà Smile runtime có thể đã mutate về trang 1) — nếu `page > lastPage` thì map 0 products từ DTO, giữ nguyên `page`/`page_size` requested + `total_count` thật.
   - **B (report upstream)**: issue cho Smile Elasticsuite kèm probe 1–3 (bằng chứng đã đầy đủ). Không loại trừ — có thể làm thêm sau fix A (upstream vẫn còn quirk ở tầng vendor).
   - **C (không fix)**: bị loại — response nói sai về window đã phục vụ.
4. Nếu fix: thêm regression test + live verify đúng quy trình (red trước, green sau). Đã thực hiện — xem Fix Evidence.

## Fix Evidence (Implementation AI — 2026-09-08)

**Implementation** (`SearchService.php`, commit `6e91e0a7`): sau `load()`, hoist
`$totalCount = (int) $collection->getSize()` (count query độc lập với window — vẫn
trả total thật kể cả khi runtime đã reset window về trang 1, theo probe 1),
`$lastPage = (int) ceil($totalCount / max(1, $criteria['page_size']))`; nếu
`$criteria['page'] > $lastPage` → `$products = []` (bỏ qua hoàn toàn items đã load —
không bao giờ map sản phẩm của trang khác), ngược lại giữ nguyên filter
`ProductInterface` như cũ. DTO nhận `$totalCount` đã hoist. `page_size >= 1` là
bất biến của parser (`boundedInt(..., 1, getMaxPageSize, ...)`) nên phép chia an toàn.

**Unit regression** (`SearchServiceTest`): 6 test mới —
`testPageBeyondLastPageServesEmptyItems` + `testPageFarBeyondLastPageWithinParserCapServesEmptyItems`
(**RED trên code pre-fix**: trả page-1 item thay vì `[]` — đúng bug live),
`testLastValidPageUnaffectedByBeyondRangeGuard`, `testInRangePageUnaffectedByBeyondRangeGuard`,
`testZeroResultSearchServesEmptyItemsOnAnyPage` (guard-rail, pass cả pre/post),
mô phỏng quirk qua `$collectionSize=6` + items trang 1. Focused: 24/24 PASS (54 assertions).
Full `Secomm_AiCommerce` suite: **132/132 PASS** (214 assertions). PHPCS Magento2:
0 errors / 0 warnings. `setup:di:compile`: PASS (không đổi DI).

**Live verification** (local stack mirror audit environment, 2026-09-08, sau khi
clean cache tag `secomm_aic` — ResponseCache không có dedicated cache type nên
`cache:clean` CLI không tác động được):

| Case | Query | Kết quả |
|---|---|---|
| A | `q=Terra&page_size=1&page=6` | 200; `total_count:6, page:6, page_size:1`; items=`[fireplace-ground-grey]` (trang cuối hợp lệ nguyên vẹn) |
| B | `q=Terra&page_size=1&page=7` | 200; `total_count:6, page:7, page_size:1`; **`items: []`** (pre-fix: trả `dinnerware-terra-collection` trang 1) |
| C | `q=Terra&page_size=1&page=20` | 200; `total_count:6, page:20, page_size:1`; `items: []` |
| D1 | `q=zzznomatch&page_size=20&page=1` | 200; `total_count:0, page:1`; `items: []` |
| D2 | `q=zzznomatch&page_size=20&page=2` | 200; `total_count:0, page:2`; `items: []` |
| Spot in-range | `q=Terra&page_size=1&page=1` / `page=2` | items đúng known-good (`dinnerware-terra-collection` / `dinnerware-terra-mug`) — guard không phá trang trong phạm vi |

Quirk tầng Smile vẫn còn (guard là module-level) — đã ghi nhận trong
`CONTINUOUS_LEARNING.md` CL-0005; phương án B (report upstream) vẫn mở nếu TL muốn.

## Notes
- llms.txt hiện không đưa ra claim nào về trang ngoài phạm vi → không có hướng dẫn sai nào cần thu hồi trong khi chờ quyết.
- Không ảnh hưởng 3 defects đã fix (BUG-7M4KQX / BUG-K8T3WR / BUG-V2N9DL — đều verify ở page mặc định) và không ảnh hưởng known-good pagination trong phạm vi trang.
