---
id: DEC-TASKWNQCRW-001
title: 'GHN RATE weight semantics FROZEN: service_type_id = total quote weight only (<20kg→2, >=20kg→5) + per-unit weight gate merchant-tunable (max_package_weight_g, default 50000g); RATE ≠ CREATE truth'
status: accepted             # TL brief frozen rules 2026-10-01 + plan approval + user decision (config default 50000g)
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-10-01
created: 2026-10-01
last_verified: 2026-10-01
verified_against_commit:
supersedes: [DEC-TASKFXFMJ0-001]
superseded_by:
work_items: [TASK-WNQCRW]
---

# Decision Record: GHN RATE weight classification + CREATE parcel boundary

## Status

Accepted (2026-10-01 — TL brief "Align GHN RATE Weight Classification and CREATE Physical
Parcel Decision" frozen operational rules; user decision cùng ngày: weight gate
**merchant-tunable** qua config, default 50000g — giữ yêu cầu config field thay vì freeze
cứng). **Supersedes DEC-TASKFXFMJ0-001 ở 2 khía cạnh**: (a) "no RATE-side weight cap" —
per-unit weight gate quay lại dạng display filter merchant-tunable; (b) "type rule hiện tại
giữ nguyên" — packageCount dependency bỏ, type = total weight only. FXFMJ0 fallback rule GIỮ
Nguyên: heavy weight đơn thuần không tạo fallback eligibility; timeout/5xx/429 →
TECHNICAL_FAILURE. CREATE semantics (DEC-TASK9Q5ZAK-001) KHÔNG bị supersedes.

## Decision Type

Architecture — RATE service classification + checkout eligibility guard. Không schema, không
ShippingCore policy change (reason mới free-form → non-eligible by construction).

## Decisions

1. **RATE type = total weight only**: `getTotalWeightGrams() < 20000 → 2`, ngược lại → 5.
   CẤM packageCount/itemCount/SKU count/qty ảnh hưởng type (bỏ `packageCount === 1` của
   TASK-WAWNDS). Boundary 19.999→2, 20.000→5. Payload-safe: items[] đã guard theo type, type 2
   gửi root aggregate weight only.
2. **Per-unit weight gate merchant-tunable**: config `carriers/secomm_ghn/max_package_weight_g`
   (GRAMS, store scope, default **50000** = `GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G`,
   fallback const khi empty/≤0). 1 sellable unit > limit → UNAVAILABLE
   `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` trước mọi fee call, không fallback. Strictly `>`.
   Gate gộp `QuoteParcelEstimate::findHardLimitViolation()` (weight check TRƯỚC dimensions).
3. **Aggregate KHÔNG cap**: total > 50000g hợp lệ khi mọi unit ≤ limit → type 5 → fee call
   (sandbox-observed FXFMJ0: 70/120kg quote 200 — evidence giữ).
4. **RATE ≠ CREATE truth (freeze invariant)**: RATE service type transient, không persist, không
   reuse. CREATE derive độc lập từ `ShipmentPhysicalData`/`PhysicalPackage(s)`;
   `secomm_ghn_shipment.service_type_id` chỉ ghi từ CREATE plan. CREATE constraints frozen:
   per-package 50kg + dimension limits (TASK-ZS2B41 rev.2) + interpreter semantics.
5. **Provenance TÁCH 4 nguồn** (evidence-merging rule): (a) 20kg threshold = GHN Calculate Fee
   contract (DOCUMENTED); (b) single >50kg = GHN CREATE hard capability dùng làm checkout
   eligibility guard (merchant tunable — nâng config nếu muốn quote unit nặng để chia package
   sau); (c) aggregate >50kg RATE acceptance = SANDBOX_OBSERVED (FXFMJ0 matrix); (d) CREATE
   interpretation = Create Order contract + PhysicalPackage architecture. KHÔNG mô tả chung
   chung "GHN weight limit".
6. **Legacy knob `carriers/secomm_ghn/max_package_weight`** (không `_g`, validation-stage
   `Ghn::processAdditionalValidation`, inert — không khai báo) không đụng; docs ghi interplay.
7. **RATE_REQUEST_UNREPRESENTABLE** giữ RESERVED (no emitter — §8 "remove" đã thoả từ FXFMJ0);
   `SafeDegradationEligibilityPolicy` không đổi.

## Consequences

- Cart multi-item <20kg giờ type 2 (trước type 5 + items[]) — fee tính theo total weight,
  đúng contract docs ("2: total under 20kg"); sandbox case 2×5kg type 2 cần evidence mới.
- Merchant default 50000: unit 50.001kg+ ẩn GHN (trước quote được) — coherent với CREATE cap;
  merchant nâng config để quote lại (provider chấp nhận — FXFMJ0 evidence), CREATE vẫn chặn
  package vật lý >50kg.
- Log context weight violation dùng `value_g`/`limit_g` (dimensions giữ `value_cm`/`limit_cm`).
- Tests: FXFMJ0 premise tests (60kg single/mixed quote) re-pin — sandbox evidence chuyển thành
  explicit ctor raised-limit path.

## Verification

- Ghn suite green (+ matrix §13/§14/§15 + CREATE regression); compile OK; validator baseline
  56 không tăng; sandbox matrix 11 cases record tại `.ai/evidence/TASK-WNQCRW/evidence.md`;
  final report A-L với closure marker (FROZEN/NOT FROZEN).
