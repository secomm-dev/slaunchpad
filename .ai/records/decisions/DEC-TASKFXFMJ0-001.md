---
id: DEC-TASKFXFMJ0-001
title: 'GHN RATE weight flow: gỡ 50kg pre-gate (supersede DEC-TASKMQ2DRG-001) — Calculate Fee không có upper bound 50kg (DOCUMENTED + SANDBOX_OBSERVED); weight chỉ quyết định service_type 2/5 qua biên 20kg; CREATE cap giữ nguyên'
status: accepted             # TL brief 2026-09-30 (decision gate §7 của TASK-FXFMJ0) + sandbox evidence — TL gate tại review
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-30
created: 2026-09-30
last_verified: 2026-09-30
verified_against_commit:
supersedes: [DEC-TASKMQ2DRG-001]
superseded_by:
work_items: [TASK-FXFMJ0]
---

# Decision Record: GHN RATE weight flow — gỡ 50kg pre-gate

## Status

Accepted (2026-09-30 — TL brief "Retest GHN Calculate Fee Weight Threshold & Align RATE Flow"
với decision gate §7; gate MỞ bởi sandbox retest 12/12. TL gate Tier-2 tại review).
Supersedes DEC-TASKMQ2DRG-001 ở KHÍA CẠNH RATE weight pre-validation (mọi khía cạnh khác của
MQ2DRG — weight matrix type 2/5 pre-MQ2DRG, provenance rules — không liên quan/được giữ).

## Decision Type

Architecture — carrier RATE semantics (Secomm_Ghn), shared reason taxonomy RESERVED
(Secomm_ShippingCore, no behavior change).

## Evidence (provenance tách bạch — §17)

| Fact | Provenance |
|---|---|
| service_type 2 = total <20kg; 5 = ≥20kg OR multi-parcel | DOCUMENTED (developer.ghn.dev/en/docs/order/calculate-fee, fetch 2026-09-30) |
| calculate_fee KHÔNG có 50kg/50000g threshold trong docs | DOCUMENTED (cùng nguồn) |
| weight required non-zero; L/W/H optional; items[] = "same shape as Create Order, used for heavy goods" | DOCUMENTED (cùng nguồn) |
| Sandbox chấp nhận >50kg: single 50kg/50.001kg/60kg + aggregates 70kg (2×35kg) / 120kg (4×30kg) → HTTP 200 + total | SANDBOX_OBSERVED (2026-09-30, matrix 12/12, `.ai/evidence/TASK-FXFMJ0/sandbox_weight_matrix.php`) |
| 50kg pre-gate tại RATE (MQ2DRG) | INTERNAL BUSINESS DECISION — bị bác bỏ bởi bằng chứng trên |

## Decisions

1. **Gỡ 50kg RATE pre-gate**: xóa `QuoteParcelEstimate::findWeightLimitViolation()` +
   `GhnWeightConstraintViolation` + 2 call site trong `GhnRateCalculator` (cả 2 entry) +
   `weightLimitOutcome()` + reason `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` (no emitter).
   Rate-side weight chỉ quyết định service_type (biên 20kg, DOCUMENTED, giữ nguyên).
2. **CREATE giữ nguyên**: `TYPE_5_MAX_WEIGHT_G = 50000` vẫn là CREATE-contract cap, enforce
   fail-closed bởi `GhnPhysicalParcelInterpreter` — không đụng (§8/§14).
3. **Fallback semantics (§10)**: cart >50kg KHÔNG còn tự tạo fallback eligibility — fee SUCCESS
   → realtime (suppression như thường); provider business rejection → UNAVAILABLE không
   fallback; timeout/5xx/429 → TECHNICAL_FAILURE → policy hiện tại áp dụng. Không convert
   heavy weight thành technical.
4. **`RATE_REQUEST_UNREPRESENTABLE` → RESERVED** trong ShippingCore: const + eligibility wiring
   giữ nguyên (frozen semantics), docblock ghi no production emitter. Không xóa shared surface.
5. **Dimension policy KHÔNG đổi** (§11): dims thiếu → allowed; dims trusted vượt
   `rate_max_*_cm` → UNAVAILABLE không fallback (như trước).
6. **Type-5 items[] serialization giữ nguyên** (per-unit rows, quantity=1) — retest xác nhận
   shape vẫn được chấp nhận (không disproves).
7. Alternatives: giữ pre-gate (bác — mâu thuẫn docs + sandbox; fallback hóa >50kg aggregate là
   hành vi pricing vô căn cứ); gate per-unit 50kg riêng cho RATE (bác — provider không enforce,
   sẽ ẩn giá thật của GHN).

## Consequences

- Checkout quote >50kg (single hoặc aggregate) bằng GIÁ THẬT của GHN thay vì bị ẩn/fallback.
- Bộ reason RATE gọn lại: 2 weight-reason không còn emitter; shared reason reserved.
- Suite Ghn: các test MQ2DRG weight-matrix bị đảo ngược thành matrix §12/§13 (API-call
  assertions); `OverFiftyKgNoFallbackVerificationTest` thay bởi
  `HeavyWeightRateFlowVerificationTest` (semantics mới).
- Diện sửa: chỉ Secomm_Ghn (code + tests) + docblock RESERVED ShippingCore. Không schema,
  không contract break, không đụng CREATE/checkout khác.

## Work Items

- TASK-FXFMJ0 (implementation + sandbox evidence).
