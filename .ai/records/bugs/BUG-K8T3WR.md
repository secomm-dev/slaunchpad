---
id: BUG-K8T3WR
type: bug
title: 'AI catalog search drops price_min when price_min and price_max are combined'
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

# [SLP][BUG-K8T3WR] AI catalog search drops price_min when price_min and price_max are combined

## Overview & Bug Classification
- **Classification**: **BUG CONFIRMED** (live-proven)
- **Origin**: Independent QA Review (Post-Implementation Audit of `/ai/catalog/search`)
- **Component**: `Secomm_AiCommerce` — Service/Catalog/SearchService
- **Severity**: High (kết quả price-range sai dữ liệu — trả cả sản phẩm GIÁ CAO hơn max-bound do price_min bị mất, ví dụ request `price_min=4875000&price_max=1375000` trả y hệt kết quả `price_max`-only)

## Symptom & Reproduction Evidence
`GET /ai/catalog/search?price_min=4875000&price_max=1375000` → 200 nhưng
`total_count` và danh sách items **trùng khớp hoàn toàn** với
`GET /ai/catalog/search?price_max=1375000` — bound dưới bị bỏ ngầm.
Từng bound riêng lẻ (`price_min` alone, `price_max` alone) vẫn đúng.

## Root Cause Analysis
`SearchService` phát 2 lời gọi rời:
`addFieldToFilter('price', ['gteq' => $min])` rồi `addFieldToFilter('price', ['lteq' => $max])`.
`Smile\ElasticsuiteCatalog\...\Fulltext\Collection::addFieldToFilter()` lưu filter
theo key là mapped field name: `$this->filters[$this->mapFieldName($field)] = $condition;`
— lời gọi thứ hai **ghi đè** lời gọi thứ nhất (keyed overwrite), nên chỉ còn `lteq`.
Đây là contract của collection API, không phải bug của Elasticsuite: 2 bound cùng
một field PHẢI gửi trong MỘT condition.

## Required Fix Contract (Acceptance Criteria for Implementation AI)
1. `price_min` + `price_max` cùng lúc → MỌI item trả về thỏa CẢ HAI bound.
2. Không phá behavior của từng bound riêng lẻ.
3. Không lặng lẽ nuốt bound nào; không đổi validation của parser.

## Fix Evidence (Implementation AI — 2026-09-08)
- Gộp thành MỘT condition: `array_filter(['gteq' => $min, 'lteq' => $max], non-null)` rồi `addFieldToFilter('price', $priceCondition)` — Smile QueryBuilder map `gteq→gte`, `lteq→lte` vào CÙNG một Range query với đủ 2 bounds.
- Test regression: `SearchServiceTest::testPriceBoundsIssuedAsSingleCombinedFilter` (assert phát đúng 1 lần `addFieldToFilter('price', ['gteq' => 4875000.0, 'lteq' => 1375000.0])`) + 2 test min-only / max-only (red trước fix, green sau fix).
- Live re-verify: xem section Live Verification trong task record.
