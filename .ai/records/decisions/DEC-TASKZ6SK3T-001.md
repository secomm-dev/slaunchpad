---
id: DEC-TASKZ6SK3T-001
title: 'GetListCity direction: BC shim qua LocationHierarchyProvider + migrate FE call sites sang addressLocations (hoàn thiện TASK-YQSS3M), không harden-in-place'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-10-02
created: 2026-10-02
last_verified: 2026-10-02
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-Z6SK3T]
---

# Decision Record: GetListCity direction — BC shim + addressLocations migration

## Status

**ACCEPTED (2026-10-02 — user approval qua session "approve" trên gate này; decision owner
chỉ đạo phiên).** Steps 3-4 của TASK-Z6SK3T được mở để code theo thiết kế trong Decisions
dưới đây. Steps 1-2 (CityData N+1 + Helper single-fetch) đã triển khai trong cửa sổ plan
approval 2026-10-02 và không phụ thuộc decision này.

## Decision Type

Architecture — GraphQL surface (`GetListCity` @deprecated) + FE consumer migration. Checkout
flow (Launchpad_Osc JS copies) thuộc §12 high-risk → Tier-2.

## Context

Audit hiệu năng 2026-10-02 (TASK-Z6SK3T): SQL không phải nút thắt (0.7ms warm/query). Nút
thắt = (a) `CityData` N+1 (1.191 query/cold cache — đã fix trong cửa sổ plan approval),
(b) `GetListCity` uncached end-to-end + 6 JS call sites không memo → mỗi region change trả
full bootstrap, (c) OSC duplicate `addressSchema` ×2, (d) helper double-query (đã fix).
`addressLocations`/`addressSchema` là canonical `@cache(cacheable: true)` với engine mỏng
(`LocationHierarchyProvider::fetchChildren`). TASK-YQSS3M đã plan migration này từ
2026-08-25 nhưng chỉ hoàn thành phần cart (php-cart/shipping.phtml).

## Decisions

1. **Hướng B — hoàn thiện TASK-YQSS3M**, không harden-in-place `GetListCity`:
   resolver thành BC shim delegate `LocationHierarchyProvider::getRootLocations()`
   (mapped country), giữ legacy `CityLocaleCollection` cho unmapped country + `area=ADMINHTML`
   (BC locale + non-profile dataset). Schema.graphqls KHÔNG đổi → không `graphql:dump`.
2. **6 JS call sites** migrate sang `addressLocations` + shared cache module
   `address-location-cache.js` (Map theo `<profile>|<regionId>` + in-flight Promise dedupe +
   memo `addressSchema(countryId)` — giải quyết luôn OSC duplicate schema request).
   Post-condition: grep `GetListCity` trong app/code chỉ còn resolver + schema + tests.
3. **Root-only enforcement** trên shim (`parent_city_id IS NULL`) — không tái hiện defect
   flat mixed-tier list TASK-6MKF0V AC-3 trên profile 3-level.
4. **Không wire** `Magento_GraphQlResolverCache` identity ở đợt này (M-effort + invalidation
   design — follow-up SA optional). `@cache(cacheable: false)` giữ nguyên (FE toàn POST —
   FPC không serve POST dù flip).

## Alternatives Considered

- **Harden-in-place** (undeprecate + cache in resolver): giữ 2 engine song song
  (ORM collection + filesort) và deprecated surface sống mãi; không có client latency win
  nếu không làm memoization (vẫn phải làm step 4). Chi phí ngang, giá trị thấp hơn.
- **Wire resolver cache ngay**: cần identity map + key-factor providers + invalidation
  design (Tier-2 SA); với client memoization, benefit không đáng trong đợt này.

## Consequences

- Server hot path của mapped country hết ORM collection; legacy path chỉ còn cho
  unmapped country + admin area.
- JS một nguồn cache chung Secomm ↔ Launchpad_Osc (hết drift); số GraphQL POST trên
  mỗi region change giảm về 1 (page-session).
- Risks: shape drift nếu migrate JS sai contract (mitigate: payload addressLocations
  đủ `city_id/default_name/label`); 3-level flat-list hazard (mitigate: root-only +
  AC trong spec TASK-Z6SK3T).
