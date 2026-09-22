---
id: TASK-BE5YD2
type: task
title: 'GHTK CREATE lifecycle reliability — typed response parsing + ORDER_ID_EXIST recovery + business/technical failure split (không blind retry)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed task 2026-09-14; ORDER_ID_EXIST semantics theo official docs (SPIKE-A1DGPY §2)
specification_ref: Embedded Mini-Spec
risk: medium                  # create runtime path; native label flow được bảo vệ; recovery có identity validation fail-closed
status: dev-complete          # implemented 2026-09-14 (scoped 606 tests — 0 failure trong scope; evidence .ai/evidence/TASK-BE5YD2/); runtime ORDER_ID_EXIST shape NEEDS_RUNTIME_VERIFICATION; chờ Tier-2 review
priority: high
decision_assessment: material # ORDER_ID_EXIST chuyển từ rejection → recovery-after-validation: material behavior change → DEC-TASKBE5YD2-001 theo §38
decisions: [DEC-TASK7AJ3K8-002, DEC-TASKBE5YD2-001]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [TASK-KCXKVR, TASK-6YG3HP, TASK-W8SH0N, TASK-44F7V7, SPIKE-A1DGPY]
---

# [SLP][FEAT-YA2C0W][TASK-BE5YD2] GHTK CREATE lifecycle reliability

## Embedded Mini-Spec

### Goal

CREATE path phân loại response thành typed 4-kind result (CREATED / RECOVERED_EXISTING /
BUSINESS_REJECTION / TECHNICAL_FAILURE — carrier-owned, KHÔNG dùng CarrierRateOutcome) và xử lý
`ORDER_ID_EXIST` như recovery path khi provider identity khớp deterministic `order.id`. Single
submission attempt giữ nguyên (không blind retry). Native Magento shipment/label lifecycle giữ
nguyên. KHÔNG: ShippingCore/VietNamAddress, capability freeze, CANCEL, pickup, COD changes.

### Expected Behavior

1. NEW `Model/OrderSubmit/GhtkCreateResponse` (typed VO) + `OrderResponseMapper::parse(array
   $response): GhtkCreateResponse` thay các method rời (success/message/labelId/trackingNumber):
   - CREATED: success=true + normalized identity (partnerId, label = label||label_id, tracking =
     tracking_id→tracking_code→tracking→label per KCXKVR precedence, providerStatus); thiếu usable
     identity → MALFORMED (§18 — không tạo shipment với tracking giả).
   - DUPLICATE_EXISTING: success=false + error_code=`ORDER_ID_EXIST` (exact) → identity từ
     partner_id/ghtk_label/status (parse cả top-level lẫn order block — exact shape
     NEEDS_RUNTIME_VERIFICATION, documented).
   - BUSINESS_REJECTION: success=false + error_code khác / không parse được identity.
   - MALFORMED: success key missing/garbage.
2. Service validation (§5/§6): DUPLICATE_EXISTING → so `parsed.partnerId === submitted order.id`:
   khớp + có label → **RECOVERED_EXISTING** (reuse provider label/tracking — KHÔNG submit lại,
   KHÔNG shipment thứ hai); partner_id missing/mismatch → **hard business failure** (không recover
   âm thầm); ghtk_label missing → hard failure (không đủ identity để tiếp tục — runtime-verification
   note cho variants).
3. Transport failures theo category (§15–§17): CLIENT_ERROR/RATE_LIMIT (403/400/429) → business/
   config failure non-retry; NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → technical failure —
   CẢ HAI đều single attempt, KHÔNG auto-retry POST (§11); technical message hướng dẫn merchant
   retry an toàn cùng reference (deterministic id → recovery path — §12, đây là reliability value).
4. `OrderSubmitResult` += `recovered` + `providerStatus` (optional); shipment comment ghi nhận
   recovered. Admin retry dùng lại cùng deterministic id (generation KHÔNG đổi — §6/§12).
5. Logging (§23): operation=CREATE, order_id, classification, recovered, error_code — masked,
   không token/telephone/full address/raw payload.

### Constraints / Rules

- CarrierRateOutcome KHÔNG dùng cho CREATE (§2/§37 grep gate).
- KHÔNG sửa ShippingCore/VietNamAddress; KHÔNG freeze capability (§25); KHÔNG đụng COD (§26),
  CANCEL (§27), pickup (§28), status mapper/webhook (KCXKVR giữ), RATE classifier (W8SH0N giữ).
- Không parse message string để quyết duplicate — chỉ error_code field exact match; exact field
  name/shape = NEEDS_RUNTIME_VERIFICATION (TASK-44F7V7 follow-up).
- Test fixtures dùng fake identities (§24).

### Out of Scope

CANCEL · pickup enhancements · ORDER_ID_EXIST response variants ngoài docs · address scheme freeze ·
aggregation runtime wiring.

### Acceptance Criteria

AC-1: CREATED parse normalized identity (tracking_id precedence — test). AC-2: ORDER_ID_EXIST +
matching partner_id + ghtk_label → RECOVERED_EXISTING, shipment flow succeed, KHÔNG duplicate
shipment (test). AC-3: mismatch/missing partner_id → hard failure (tests). AC-4: missing provider
identity → hard failure (test). AC-5: business error_code khác → typed business failure (test).
AC-6: 403/400 → business/config failure non-retry; timeout/network/5xx/invalid JSON/success-thiếu-
identity → technical failure non-retry (tests). AC-7: no POST retry loop (grep + RetryPolicy
singleAttempt giữ). AC-8: retry scenario — attempt 1 technical uncertain, attempt 2 same
deterministic id → recovery (test). AC-9: KCXKVR/6YG3HP/W8SH0N regressions pass. AC-10:
ShippingCore/VN diff 0; CarrierRateOutcome 0 trong OrderSubmit* (grep).
