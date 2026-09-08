---
id: TASK-6TEBY6
type: task
title: 'Fix 3 live-proven /ai/catalog/search defects (price-sort 500, price_min dropped, category ignored) + advertise live-proven query contract in llms.txt'
project_code: SLP
parent: null
external_refs: {}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: .ai/specs/SPEC-TASK-QV3R7T-la-22-ai-commerce-read-layer.md
risk: high
status: completed
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
  - CMP-AIDISCOVERABILITY
source_areas:
  - app/code/Secomm/AiCommerce/Service/Catalog/SearchService.php
  - app/code/Secomm/AiDiscoverability/Service/Source/CommerceEndpointsSource.php
  - app/code/Secomm/AiDiscoverability/Service/LlmsTxtFormatter.php
  - .ai/records/bugs/BUG-7M4KQX.md
  - .ai/records/bugs/BUG-K8T3WR.md
  - .ai/records/bugs/BUG-V2N9DL.md
  - .ai/records/bugs/BUG-Q8L5RD.md
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: '2026-09-08'
supersedes: []
---

# [SLP][TASK-6TEBY6] Fix 3 live-proven /ai/catalog/search defects (price-sort 500, price_min dropped, category ignored) + advertise live-proven query contract in llms.txt

<!-- CANONICAL TASK RECORD — TASK-6TEBY6. Fix 3 defects audit sống của facade /ai/catalog/search (LA-22 runtime), sau đó cập nhật guidance Product Search trong llms.txt CHỈ SAU khi live re-verify pass. Record viết hồi tố 2026-09-08 theo tiến trình làm việc thực (Phase 0–8). -->

## Bối cảnh (Context)

Independent QA audit `/ai/catalog/search` trên live stack chứng minh 3 defects: (1) `sort=price_asc|price_desc` → deterministic HTTP 500; (2) `price_min` bị bỏ khi có `price_max`; (3) `category=<id>` (kể cả id không tồn tại) bị lặng lẽ bỏ qua → trả toàn catalog. Danh sách known-good (browse, `q`, pagination, sort relevance/name, single price bounds, allowlist filters, lỗi 400, store selection, cache) phải giữ nguyên. Sau khi fix pass live re-verify, `llms.txt` (Secomm_AiDiscoverability) phải quảng bá đúng contract search đã được chứng minh để AI agent không phải đoán tên tham số.

## Specification & Plan

- **Spec (MINI, VALID)**: mini-spec của từng defect = "Required Fix Contract" trong từng BUG record:
  [BUG-7M4KQX](../bugs/BUG-7M4KQX.md) (price sort 500), [BUG-K8T3WR](../bugs/BUG-K8T3WR.md) (price_min dropped), [BUG-V2N9DL](../bugs/BUG-V2N9DL.md) (category ignored).
- **Plan**: smallest-fix trực tiếp trong `SearchService::search()` — chỉ dùng storefront API của collection (addPriceData/addCategoryFilter/addFieldToFilter), không viết query engine, không đổi contract parser.
- **Phát hiện thêm trong verify**: [BUG-Q8L5RD](../bugs/BUG-Q8L5RD.md) — page ngoài phạm vi (≤ cap 50) trả items trang 1 kèm echo `page` sai window. Ngoài scope 3 defects được mandate → ghi nhận, CHỜ TL decide (không fix trong task này).

## Mode & Approach

**Mode**: B — bug-fix từ independent QA audit. Root-cause từ vendor source (không đoán hành vi core), TDD red→green cho từng fix, live re-verify full matrix trên local stack chạy đúng code đã fix (DB local = mirror của audit environment; deploy staging là human-initiated theo `.ai/AGENTS.md` — không thuộc quyền AI).

## Changes

1. `Secomm_AiCommerce/Service/Catalog/SearchService.php`:
   - `addPriceData(Group::NOT_LOGGED_IN_ID, $websiteId)` trước sort — pin customer group guest cho nested price sort của Smile collection (BUG-7M4KQX).
   - Category: resolve qua `CategoryRepositoryInterface::get($id, $storeId)`; id không tồn tại (`NoSuchEntityException`) → `InvalidParameterException` (400); category hợp lệ → `addCategoryFilter($category)` (engine-level) thay vì bị lặng lẽ bỏ qua (BUG-V2N9DL).
   - Price bounds: MỘT condition tổng hợp `['gteq'=>…,'lteq'=>…]` qua `addFieldToFilter('price', …)` — tránh overwrite do collection lưu filter theo key mapped field name (BUG-K8T3WR).
   - Catch cụ thể `InvalidParameterException` (rethrow) TRƯỚC `catch (LocalizedException)` — 400 không bị re-wrap thành 503.
2. `Secomm_AiCommerce/Test/Unit/Service/Catalog/SearchServiceTest.php`: +4 regression tests (price sort context, single combined price filter, category resolve+apply, unknown category → invalid_parameter), red trên code cũ 4/4, green sau fix.
3. `Secomm_AiDiscoverability/Service/Source/CommerceEndpointsSource.php`: usage guidance `### Product Search` — 12 dòng quảng bá đúng contract live-proven (`q`, `category` + 400 unknown id, price bounds both/all, sort allowlist 5 giá trị + ghi chú price index, `filter[ATTRIBUTE]=OPTION_ID` theo allowlist config, page/page_size defaults 1/20 + max từ config, `store`, unknown param → 400) + 2 example GET build từ URL effective (không hardcode domain/path/store/category/color id).
4. `Secomm_AiDiscoverability/Service/LlmsTxtFormatter.php`: render `usage` sau `Purpose:` — sanitize HTML/control chars nhưng GIỮ literal `filter[…]`; merchant text vẫn escape bracket như cũ.
5. `Secomm_AiDiscoverability/CHANGELOG.md` 1.6.0; `Secomm_AiCommerce/CHANGELOG.md` 1.2.1; `.ai/project-context/memory/CONTINUOUS_LEARNING.md` CL-0004 + CL-0005.

## Test & Static Evidence

- `Secomm_AiCommerce` unit: **127/127 PASS** (189 assertions). `Secomm_AiDiscoverability` unit: **77/77 PASS** (217 assertions). PHPCS Magento2: 0 errors / 0 warnings trên toàn bộ file đổi. `di:compile` PASS.
- llms.txt: `testUsageAdvertisesConfiguredAllowlistAndBound`, `testUsageOmitsFilterLineWhenAllowlistEmpty`, `testUsageGuidanceRendersLiterallyAfterPurpose`, `testAdvertisesConfiguredBasePath` (usage theo store-scoped path).

## Live Verification (local stack chạy code fix — mirror audit environment)

| Kịch bản | Kết quả |
|---|---|
| `sort=price_asc` / `price_desc` | 200; 144/143 giá monotonic theo **price index** guest group (0 violation) |
| `price_min=4875000&price_max=1375000` | 43 items vs 80 khi chỉ `price_max` → price_min ĐƯỢC áp dụng; 0/43 ngoài bounds |
| `category=19` | 25/25 items thuộc category tree; `category=999999` → 400 invalid_parameter |
| Known-good: browse, `q`, page/page_size, camelCase `pageSize`→400, relevance, name sorts, filter id + allowlist reject, combined đa chiều, sort/param/store sai → 400 | PASS toàn bộ |
| `/llms.txt` regenerated | `### Product Search` đủ 12 dòng usage + 2 example; Example 1 (`q=Terra&sort=price_asc`) và Example 2 (`category=6&filter[color]=20&price_min=1&price_max=10000&page=2&page_size=20`) chạy live 200 đúng nghĩa |

## Known Limitations / Follow-up

- BUG-Q8L5RD (page ngoài phạm vi → items trang 1) — **done**: TL chọn Option A (module-level guard), fixed trong follow-up commit `6e91e0a7`; bằng chứng đầy đủ trong [BUG-Q8L5RD](../bugs/BUG-Q8L5RD.md) §Fix Evidence.
- Deploy staging/prod: human-initiated, ngoài task này.
