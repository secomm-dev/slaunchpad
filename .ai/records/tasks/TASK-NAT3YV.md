---
id: TASK-NAT3YV
type: task
title: 'Phase E-C1 — Common carrier rate outcome semantics trong Secomm_ShippingCore (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE, contracts only)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-NAT3YV — outcome shape theo approved E-C1 directive (SPIKE-YH439T basis); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-NAT3YV-shippingcore-carrier-rate-outcome.md
risk: medium                  # additive contracts/VOs; chưa có runtime consumer; 0 carrier code
status: in_progress
priority: high
decision_assessment: none-material   # failure model 3-state đã approved SPIKE-YH439T; service-level/carrier-identity Option B theo directive — không DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [SPIKE-YH439T, TASK-XXBN5X, TASK-T78YH6]
---

# [SLP][FEAT-YA2C0W][TASK-NAT3YV] Phase E-C1 — Common carrier rate outcome semantics trong Secomm_ShippingCore (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE, contracts only)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-NAT3YV-shippingcore-carrier-rate-outcome.md, FULL)*

### Goal

Contract provider-neutral cho realtime carrier báo kết quả tính giá — thay `false/null/throw/
fake-rate/log-and-continue` bằng domain outcome: `SUCCESS` (rate dùng được) · `UNAVAILABLE`
(không phục vụ vì lý do KHÔNG-tạm-thời — business/data/config; KHÔNG mở fallback) ·
`TECHNICAL_FAILURE` (lỗi kỹ thuật tạm thời — có thể góp vào fallback eligibility sau). Phân biệt
này là input E-SL1; KHÔNG orchestration trong E-C1.

### Expected Behavior

1. `CarrierRate` (Api\Rate + Model\Rate): amount **>= 0** (zero HỢP LỆ — promotion; negative →
   LogicException) + currency nullable — asymmetric có chủ đích với `FallbackRate` (strictly
   positive vì no-match ⇒ null; realtime 0đ hợp lệ — directive §14).
2. `CarrierRateOutcome`: 3 STATUS constants + 5 shared REASON constants (2 giá trị
   `CANONICAL_UNRESOLVED`/`UNSUPPORTED_DESTINATION` cố ý trùng handoff constants — translate
   giữ nguyên); carrier-specific detailed reasons là free string của carrier (không centralize).
   Invariants: SUCCESS ⇒ rate non-null + reason null; UNAVAILABLE/TECHNICAL_FAILURE ⇒ rate null
   + reason optional ('' normalize null); unknown/impossible → `LogicException`. Public
   constructor + named factories `success()/unavailable()/technicalFailure()` + `isSuccessful()`
   hard-guard (mirror `ResolvedShippingAddress` precedent).
3. Classification ownership: carrier adapter phân loại raw provider errors (timeout/5xx →
   TECHNICAL_FAILURE; route-not-supported/dimension/mapping-missing → UNAVAILABLE) — ShippingCore
   không hiểu raw error codes. Provider mapping missing → UNAVAILABLE +
   `REASON_PROVIDER_MAPPING_MISSING` (deterministic data/config, không fallback). Handoff
   unresolved (không fallback-eligible) → UNAVAILABLE + `REASON_CANONICAL_UNRESOLVED`.
   Auth/config failure → **UNAVAILABLE, không bao giờ TECHNICAL_FAILURE** (fallback không che
   lỗi cấu hình merchant — fail loudly ở log/ops).
4. Service level + carrier identity ĐỨNG NGOÀI outcome (Option B — caller/orchestration đã biết
   bucket + carrier; DTO tái sử dụng). Không metadata array; không logger trong VO (§16); không
   Magento RateResult dependency (§19).

### Constraints / Rules

- KHÔNG: carrier adoption, service-level aggregation/selection, fallback trigger +
  FallbackRateProvider invocation, Mageplaza bridge, checkout methods/showmethod policy,
  external resolver, OrderOperations, rate selection (cheapest/preferred/fastest).
- KHÔNG status thứ 4 (BUSINESS_REJECTION/CONFIG_ERROR/… là UNAVAILABLE + reason — directive §3).
- Fallback pricing (sau này) KHÔNG được convert thành `CarrierRateOutcome::SUCCESS` cho carrier
  giả — FallbackRate là rate source riêng ở service-level orchestration (§23).
- Namespace mới `Api\Rate` + `Model\Rate` (mirror precedent Api\Address/Tracking/Fallback);
  DI preference 2 interface (mirror E-A).

### Out of Scope

Carrier adoption · aggregation · fallback policy · Mageplaza bridge · checkout/showmethod ·
external resolver · OrderOperations · rate selection · logging policy (log ở carrier/API ops
boundary; orchestration có thể log aggregate sau).

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-NAT3YV (tóm tắt): constants đúng (2 giá trị trùng handoff documented) ·
invariants fail-fast + factories · CarrierRate zero-valid/negative-reject · isSuccessful matrix ·
grep không RateResult/logger/metadata · DI + compile + validator 0 new finding + phpunit pass ·
README/CHANGELOG (3-state rule + auth/config non-technical + Stage 1/2/3 + mixed-outcome +
fallback≠SUCCESS) + working memory sync + 0 carrier code.

## Plan

`../plans/TASK-NAT3YV-implementation-plan.md`

## Review rounds

- **r1 (2026-09-08, TL/SA review)** — 2 cleanup trước contract freeze:
  **(A) `FallbackRate` amount >= 0** (thuộc TASK-XXBN5X r2 — zero là valid explicit rate,
  asymmetric `CarrierRate` vs `FallbackRate` bị REJECT; null duy nhất nghĩa "no rate").
  **(B) Shared failure reasons MỘT canonical owner**: `Api\Failure\ShippingFailureReason`
  (5 constants) — `CarrierAddressHandoffInterface` + `CarrierRateOutcomeInterface` bỏ duplicate
  constants, reference owner; bỏ parity test (mục đích duy nhất là chứng minh duplicate bằng
  nhau); taxonomy KHÔNG mở rộng carrier-specific (GHN_LOCATION_NOT_FOUND… stay carrier-owned).
  Semantics KHÔNG đổi: 3-status taxonomy, `unavailable(null)`/`technicalFailure(null)` valid,
  status = orchestration semantic / reason = diagnostic (E-SL1/2 KHÔNG được infer fallback từ
  reason string — `UNAVAILABLE` + string `TECHNICAL_ERROR` vẫn là UNAVAILABLE), auth/config →
  UNAVAILABLE, provider mapping missing → UNAVAILABLE + PROVIDER_MAPPING_MISSING. 0 carrier code.
