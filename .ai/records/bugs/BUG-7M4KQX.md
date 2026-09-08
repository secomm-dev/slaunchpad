---
id: BUG-7M4KQX
type: bug
title: 'AI catalog search crashes with HTTP 500 internal_error on price_asc / price_desc sort'
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

# [SLP][BUG-7M4KQX] AI catalog search crashes with HTTP 500 internal_error on price_asc / price_desc sort

## Overview & Bug Classification
- **Classification**: **BUG CONFIRMED** (live-proven)
- **Origin**: Independent QA Review (Post-Implementation Audit of `/ai/catalog/search`)
- **Component**: `Secomm_AiCommerce` — Service/Catalog/SearchService
- **Severity**: High (mọi request sort theo giá đều 500 → tính năng sort theo giá chết hoàn toàn)

## Symptom & Reproduction Evidence
`GET /ai/catalog/search?sort=price_asc` → **500** `{"error":{"code":"internal_error","message":"Internal error."}}`
`GET /ai/catalog/search?sort=price_desc` → **500** (tương tự)
`GET /ai/catalog/search?q=terra&sort=price_asc` → **500** (có `q` vẫn crash).
Các request không sort theo giá (relevance, name_asc/name_desc) đều 200.

## Root Cause Analysis
`SearchService` không gọi `addPriceData()` trên collection. Runtime collection là
`Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection`
(preference của `Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection`).
Khi sort theo `price`, `prepareSortOrders()` của collection này đọc
`$this->_productLimitationFilters['customer_group_id']` **không có fallback**
(Smile collection, `prepareSortOrders` — dòng ~759) để build nested price sort
(`price.price` + nested filter `price.customer_group_id`). Không có key đó →
undefined index → `\Throwable` → controller trả `internal_error` 500.
Đối chứng: `_prepareStatisticsData` cùng file CÓ fallback `?? CustomerGroup::NOT_LOGGED_IN_ID` — chỉ sort là đọc trần.

## Required Fix Contract (Acceptance Criteria for Implementation AI)
1. `sort=price_asc` / `price_desc` trả 200 với thứ tự giá đúng chiều.
2. Chỉ dùng storefront API (`addPriceData`) — không tự viết query engine.
3. Không đổi hành vi các case còn đúng (relevance/name sorts, filters, pagination).

## Fix Evidence (Implementation AI — 2026-09-08)
- `SearchService::search()` gọi `$collection->addPriceData(Group::NOT_LOGGED_IN_ID, (int) $store->getWebsiteId())` — pin guest group + website của store view đang phục vụ (read layer là anonymous). Không có side-effect SQL join: `_applyProductLimitations()` của core early-return vì Smile collection không set `category_id`/`visibility` trong `_productLimitationFilters` theo nghĩa SQL.
- Test regression: `SearchServiceTest::testPriceSortAppliesGuestPriceContext` (red trước fix — 4/4 regression tests fail trên code cũ, green sau fix).
- Live re-verify: xem section Live Verification trong task record.
