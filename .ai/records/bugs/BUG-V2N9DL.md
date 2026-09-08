---
id: BUG-V2N9DL
type: bug
title: 'AI catalog search ignores category filter — valid and nonexistent category both return full catalog'
project_code: SLP
parent: null
external_refs: {}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: SPEC-TASK-QV3R7T
risk: high
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
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: '2026-09-08'
supersedes: []
---

# [SLP][BUG-V2N9DL] AI catalog search ignores category filter — valid and nonexistent category both return full catalog

## Overview & Bug Classification
- **Classification**: **BUG CONFIRMED** (live-proven)
- **Origin**: Independent QA Review (Post-Implementation Audit of `/ai/catalog/search`)
- **Component**: `Secomm_AiCommerce` — Service/Catalog/SearchService
- **Severity**: High (browse theo category của AI agent trả sai toàn bộ — luôn full catalog; category không tồn tại cũng trả full catalog thay vì rỗng/lỗi có bounds)

## Symptom & Reproduction Evidence
`GET /ai/catalog/search?category=41` → 200 `total_count=138` (full catalog — không hề bị giới hạn theo category 41).
`GET /ai/catalog/search?category=999999` → 200 `total_count=138` (full catalog — category không tồn tại bị lặng lẽ bỏ qua).

## Root Cause Analysis
`SearchService` gọi `addCategoriesFilter(['eq' => [$id]])` — API SQL-oriented của
core `AbstractCollection` (tạo WHERE trên bảng category quan hệ). Trên runtime
collection `Smile\ElasticsuiteCatalog\...\Fulltext\Collection`, constraint thật
đi qua engine: `addCategoryFilter(\Magento\Catalog\Model\Category $category)` →
`addFieldToFilter('category_ids', $categoryId)` (`_renderFiltersBefore` →
`prepareRequest()` → search engine). `addCategoriesFilter()` bị engine collection
**lặng lẽ bỏ qua** (không effect, không exception) → request không hề có category
constraint → full catalog.

## Required Fix Contract (Acceptance Criteria for Implementation AI)
1. `category=<id thật>` → kết quả BỊ GIỚI HẠN trong category đó (khác full catalog).
2. `category=<id không tồn tại>` → hành vi có bounds, được tài liệu hóa: 400
   `invalid_parameter` (không bao giờ lặng lẽ trả full catalog).
3. Delegation đúng storefront API: `CategoryRepositoryInterface::get($id, $storeId)` + `addCategoryFilter($category)`.

## Fix Evidence (Implementation AI — 2026-09-08)
- `SearchService::search()` resolve category qua `CategoryRepositoryInterface::get((int) $criteria['category_id'], $storeId)`; `NoSuchEntityException` → `InvalidParameterException` (400 `invalid_parameter`, được bắt rethrow TRƯỚC catch `LocalizedException` tổng để không bị bọc nhầm thành `SearchUnavailableException`); rồi `$collection->addCategoryFilter($category)` — engine-level constraint đúng storefront/category-list dùng.
- Tests regression: `SearchServiceTest::testCategoryFilterResolvesAndAppliesCategory` + `testUnknownCategoryIsRejectedAsInvalidParameter` (red trước fix — 4/4 fail trên code cũ, green sau fix).
- Live re-verify: xem section Live Verification trong task record.
