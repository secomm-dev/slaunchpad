---
id: TASK-KCXKVR
type: task
title: 'GHTK P0 API correctness — tracking/webhook status semantics + CREATE request shape/weight (SPIKE-A1DGPY findings)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed P0 fix task 2026-09-14 theo SPIKE-A1DGPY findings (official docs verified)
specification_ref: Embedded Mini-Spec
risk: medium                  # carrier runtime behavior fix theo official docs; 0 ShippingCore change; testable unit-level
status: dev-complete          # implemented 2026-09-14 (scoped 571 tests — 0 failure trong scope; evidence .ai/evidence/TASK-KCXKVR/); chờ Tier-2 review
priority: high
decision_assessment: none-material # bug-fix thực thi SPIKE-A1DGPY evidence; không mở architecture mới; DEC không cần
decisions: [DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [SPIKE-A1DGPY, TASK-7AJ3K8]
---

# [SLP][FEAT-YA2C0W][TASK-KCXKVR] GHTK P0 API correctness — tracking/webhook status semantics + CREATE request shape/weight

## Embedded Mini-Spec

### Goal

Sửa 3 P0 API correctness defects đã được official GHTK docs xác nhận (SPIKE-A1DGPY): (A1) bảng
status mapping sai semantics + thiếu code 21; (A2) webhook parser chỉ nhận JSON + bỏ sót
`action_time`; (B1) CREATE request sai shape (`{order{…}, products[]}` + `order.id` +
`products[].weight` theo kilogram). KHÔNG đụng ShippingCore; KHÔNG làm P1 (capability migration,
COD ownership, ORDER_ID_EXIST recovery, cancel, rate-outcome classification…).

### Expected Behavior

1. `GhtkStatusMapper` theo bảng chính thức api.ghtk.vn (SPIKE-A1DGPY §6): 4→OUT_FOR_DELIVERY,
   6→DELIVERED (giữ terminal — không downgrade 5→6), 7→DELIVERY_FAILED (pickup-failure,
   non-terminal + raw message), 9→DELIVERY_FAILED, 11→RETURNED, 12→PICKING, 13→RETURNED
   (bồi hoàn — best existing), 21→RETURNED (mới); 8/10 giữ PICKING/OUT_FOR_DELIVERY với comment
   đúng; shipper-info codes (123/127/128/45/49/410) → UNKNOWN (không map).
2. Webhook: parse CẢ `application/x-www-form-urlencoded` và JSON (docs dùng form trong sample;
   JSON sample cũng tồn tại); `action_time` (ISO 8601) → `occurredAt` (invalid/missing → null);
   HTTP response semantics giữ nguyên luôn-200 (duplicate/unknown → 200; lý do: GHTK chỉ retry
   đúng 1 lần nên non-200 không giữ update lâu — reconciliation cron là safety net; documented).
3. CREATE: payload `{"order": {…}, "products": [...]}`; partner key = `order.id`; products
   `weight` = **kilogram** (official docs verbatim "Product weight in kilograms"; `weight_option`
   OMIT — default kilogram, tránh undocumented interaction); `total_weight` = double kg
   (boundary conversion gram→kg tại mapper — §17 provider-owned); response đọc thêm
   `order.tracking_id`. `ORDER_ID_EXIST` vẫn bị coi là rejection (P1 follow-up — documented).

### Constraints / Rules

- Source of truth: `.ai/evidence/SPIKE-A1DGPY/evidence.md` + official docs; implementation cũ
  KHÔNG phải evidence khi conflict. Note: directive text ghi products weight "GRAM" nhưng
  official docs fetch 2 lần (2026-09-14) khẳng định kilogram → theo docs (đúng §3 của directive).
- KHÔNG đụng ShippingCore/VietNamAddress; KHÔNG đổi address adapter/TEXT_NATIVE/district strategy;
  KHÔNG đổi partner-id generation; KHÔNG thêm fields undocumented.
- Không log Token/raw payload/địa chỉ đầy đủ; giữ MaskingLogger.
- Webhook luôn-200 behavior được giữ + documented (không đổi lifecycle policy trong task này).
- Không commit/push — Tier-2 review gate.

### Out of Scope

ShippingCore v5 alignment (per-operation capability migration) · COD ownership migration ·
ORDER_ID_EXIST recovery · business-error classification · CANCEL integration · pickup UI/sync ·
TestConnection redesign · print-label API · getAddressLevel4 · district/probe gating.

### Acceptance Criteria

AC-1: mapper — 4/6/7/9/11/12/13/21 map đúng hướng mới; 5 giữ DELIVERED; unknown + shipper-info →
UNKNOWN (tests). AC-2: 12 KHÔNG còn RETURNING; 21 KHÔNG còn UNKNOWN; terminal không downgrade
(6 sau 5 giữ DELIVERED — processor sticky). AC-3: webhook + tracking API cùng MỘT mapper
(grep: 0 raw status comparison khác). AC-4: parser nhận form-urlencoded + JSON; action_time
valid → occurredAt; invalid → null (tests). AC-5: CREATE payload `{"order":{},"products":[]}`
+ `order.id` + products weight kg + `total_weight` kg + KHÔNG còn `partner_order_id`/
`weight_option` (tests). AC-6: response parse `tracking_id` (test). AC-7: native label flow,
COD behavior, legacy pickup flow regression pass (tests cũ update đúng shape). AC-8: scoped
suite pass; grep gates (1 mapping source; 0 flattened payload; 0 kg-leak; 0 ShippingCore diff).

## Plan

`../plans/TASK-KCXKVR-implementation-plan.md`
