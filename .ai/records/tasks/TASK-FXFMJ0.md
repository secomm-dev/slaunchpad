---
id: TASK-FXFMJ0
type: task
title: Retest GHN Calculate Fee Weight Threshold & Align RATE Flow
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-09-30
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-TASKFXFMJ0-001]
decision_assessment: material
decision_refs: [DEC-TASKFXFMJ0-001]
related_tickets: [TASK-MQ2DRG, TASK-WAWNDS]
components: [CMP-GHN, CMP-SHIPPING]
source_areas:
  - app/code/Secomm/Ghn/
  - app/code/Secomm/ShippingCore/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
---

# [SLP][TASK-FXFMJ0] Retest GHN Calculate Fee Weight Threshold & Align RATE Flow

## Summary

Brief TL 2026-09-30: nghi ngờ RATE đang áp 50kg pre-gate (decree của DEC-TASKMQ2DRG-001) chặn
calculate_fee trong khi Calculate Fee contract KHÔNG có 50kg threshold. Flow bắt buộc: audit →
docs verify → sandbox retest matrix A-L → chỉ sửa code nếu sandbox xác nhận >50kg accepted.

## Mini Spec

### Goal

- Xác định bằng bằng chứng 3 nguồn (code / docs / sandbox) xem 50kg RATE pre-gate có chính đáng
  không; nếu không → gỡ pre-gate, giữ nguyên CREATE limits + dimension policy + service-type rule.

### Expected Behavior

- weight ≤0 → invalid, không provider call. Dims vượt RATE limit → UNAVAILABLE (giữ nguyên).
- <20kg + 1 package → type 2; ≥20kg OR multi-parcel → type 5 → gọi calculate_fee.
- KHÔNG còn rule RATE: total >50kg → block/fallback; single unit >50kg → block.
- Heavy weight đơn thuần không tạo fallback eligibility; timeout/5xx/429 giữ TECHNICAL_FAILURE.

### Constraints / Rules

- KHÔNG đụng CREATE physical limits (50kg/package), dims policy, address, COD, tracking.
- Type-5 items[] serialization per-unit qty=1 giữ nguyên trừ khi retest bác bỏ.
- RATE_REQUEST_UNREPRESENTABLE (ShippingCore): audit consumers trước khi quyết định keep-reserved
  hay xoá (GHN hiện là emitter production DUY NHẤT).

### Out of Scope

- CREATE shipment, PICK_PRIMARY, canonical zones, TableRate pricing, admin shipment flow.

### Acceptance Criteria

- AC-001: Audit hiện trạng chính xác file:line (50kg gates inventory).
- AC-002: Docs calculate_fee verified (URL + date) — weight required, service type 2/5 @20kg,
  dims optional, KHÔNG 50kg threshold.
- AC-003: Sandbox matrix A-L chạy được và ghi kết quả (service_type, root weight, items shape,
  HTTP, code, message, total).
- AC-004..014: theo Definition of Done của brief (chỉ áp khi gate mở).

## Verification & Test Results

Dev-complete 2026-09-30 — xem `.ai/evidence/TASK-FXFMJ0/evidence.md`. Tóm tắt:

- **Gate §7 MỞ**: sandbox matrix 12/12 HTTP 200 (single 50kg/50.001kg/60kg, aggregate
  70kg/120kg) — credential probe đầu tiên 401 là DO script đọc token RAW encrypted (backend
  model Encrypted); sửa đúng qua `Config::getApiToken()` (decrypt) là chạy.
- Docs verified: calculate_fee KHÔNG có 50kg threshold; 20kg boundary = DOCUMENTED.
- Code: gỡ `findWeightLimitViolation` + VO + 2 gate + `weightLimitOutcome` + reason;
  docblocks provenance rewritten (§16); `RATE_REQUEST_UNREPRESENTABLE` → RESERVED (ShippingCore
  docblock only). CREATE không đụng (§14 regression có sẵn: interpreter 50000g boundary +
  reject message test).
- Tests: rate test dir **97 OK** (matrix §12/§13 mới với API-call assertions cho 60kg/2×35kg/
  2×5kg/20kg-boundary; mixed 80kg; standalone full-flow; `HeavyWeightRateFlowVerificationTest`
  thay `OverFiftyKgNoFallbackVerificationTest`); ShippingCore/Cod suites + compile + validator
  xem evidence (cập nhật ở bước regression cuối).

## Notes for TL Review

> **Cần input**: GHN sandbox Token + ShopId hợp lệ (admin config hoặc chuyển TL), sau đó rerun
> matrix → nếu confirm >50kg accepted → mở gate implement (§7-19). DEC supersede DEC-TASKMQ2DRG-001
> sẽ viết khi gate mở.
