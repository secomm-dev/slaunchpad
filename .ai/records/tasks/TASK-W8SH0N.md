---
id: TASK-W8SH0N
type: task
title: 'GHTK RATE error classification — CarrierRateOutcome SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE theo official semantics (SPIKE-A1DGPY)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed correctness task 2026-09-14; classification theo v5 outcome contracts hiện hữu
specification_ref: Embedded Mini-Spec
risk: medium                  # rate runtime path — nhưng customer-facing behavior giữ nguyên (rate/rate-unavailable); classification là orchestration-internal
status: dev-complete          # implemented 2026-09-14 (scoped 596 tests — 0 failure trong scope; evidence .ai/evidence/TASK-W8SH0N/); chờ Tier-2 review
priority: high
decision_assessment: none-material # thực thi v5 outcome contracts hiện hữu (TASK-NAT3YV); không DEC mới
decisions: [DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [SPIKE-A1DGPY, TASK-KCXKVR, TASK-6YG3HP, TASK-44F7V7]
---

# [SLP][FEAT-YA2C0W][TASK-W8SH0N] GHTK RATE error classification → CarrierRateOutcome v5

## Embedded Mini-Spec

### Goal

RATE path của `Secomm_Ghtk` phân loại response/error thành shared
`CarrierRateOutcomeInterface` (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE — v5 TASK-NAT3YV contracts
hiện hữu) để fallback semantics đúng: TECHNICAL_FAILURE → fallback contributor
(`hasTechnicalFailure`), UNAVAILABLE → không bao giờ. KHÔNG sửa ShippingCore/VietNamAddress,
KHÔNG freeze scheme, KHÔNG đụng CREATE idempotency/CANCEL/pickup.

### Expected Behavior

1. NEW `Model/Rate/GhtkFeeResponse` (carrier-owned VO): parse kết quả của
   `FeeResponseMapper::parse()` — KIND_SUCCESS (fee payload) / KIND_BUSINESS_REJECTION
   (HTTP 200 + success:false + error_code/message) / KIND_MALFORMED (HTTP 200 structurally
   unusable). `FeeResponseMapper` KHÔNG còn trả null cho mọi failure (§26).
2. NEW `Model/Rate/GhtkRateOutcomeFactory` (classifier → shared outcome):
   - SUCCESS: success=true + fee payload valid + delivery=true → `CarrierRateOutcome::success`
     (amount do `RateComposer::compose` — composition semantics KHÔNG đổi);
   - KIND_BUSINESS_REJECTION và `!delivery` → `unavailable(SERVICE_UNAVAILABLE)` (+error_code
     diagnostic — không control-flow string);
   - KIND_MALFORMED → `technicalFailure(TECHNICAL_ERROR)` (§12 — unusable technical data);
   - transport `GhtkApiException` theo shared category: NETWORK/SERVER_ERROR/TIMEOUT →
     `technicalFailure(TECHNICAL_ERROR)`; CLIENT_ERROR (covers 400/403) →
     `unavailable(SERVICE_UNAVAILABLE)` (403 auth = merchant/config — §7); RATE_LIMIT (429,
     undocumented) → `unavailable(SERVICE_UNAVAILABLE)` conservative — documented
     NEEDS_PROVIDER_VERIFICATION.
3. `GhtkApiException` expose `category` (optional ctor param — backward compatible); client
   `wrap()` truyền shared category qua. Retry policy GIỮ NGUYÊN (safeRead: NETWORK/SERVER_ERROR/
   TIMEOUT retry; business/403/429/invalid-JSON no retry — đúng §18).
4. `Carrier\Ghtk::collect()` dùng factory → log classification (RATE, status, reason, error_code —
   safe fields) → customer-facing vẫn `hide()`/rate (§27 — binary rate availability, không expose
   error text). Outcome ready cho E-C1 aggregation (runtime wiring của aggregator = composition
   follow-up; tests prove qua REAL `ServiceLevelRateAggregator`).
5. Canonical address failures (AMBIGUOUS/UNMAPPED) xảy ra TRƯỚC API call — không bị classify thành
   GHTK business errors (§16 — adapter null path giữ nguyên).

### Constraints / Rules

- ShippingCore/VietNamAddress: 0 diff (§31/§32). Fee composition amount semantics KHÔNG đổi (§13).
- KHÔNG: message-string parsing để classify; HTTP 200 = success; all-exceptions→1 bucket;
  null-cho-mọi-error; GHTK error_code rò vào ShippingCore (chỉ vào failureReason diagnostic field —
  carrier-owned detail được phép theo CarrierRateOutcomeInterface docblock).
- KHÔNG đụng: status mapper/webhook (KCXKVR), per-op handoff/COD (6YG3HP), capability/scheme,
  CREATE response classification (chỉ minimal nếu shared code bắt buộc — hiện không), ORDER_ID_EXIST,
  CANCEL, pickup.
- Config/profile: không đổi.

### Out of Scope

E-C1/E-SL runtime aggregation wiring vào checkout · ORDER_ID_EXIST recovery · CANCEL · pickup UI ·
address scheme freeze · CREATE classifier.

### Acceptance Criteria

AC-1: fee hợp lệ → SUCCESS outcome + rate Magento như cũ (test). AC-2: HTTP 200 success=false →
UNAVAILABLE + error_code diagnostic; không fallback eligibility (test). AC-3: malformed/missing fee
→ TECHNICAL_FAILURE (test). AC-4: transport NETWORK/SERVER_ERROR/TIMEOUT → TECHNICAL_FAILURE;
CLIENT_ERROR (403/400) + RATE_LIMIT (429) → UNAVAILABLE (tests). AC-5: retry unchanged — network/5xx
retry theo policy; business/403/429 no retry (client tests giữ). AC-6: real-aggregator integration:
business → hasTechnicalFailure=false; technical → true; SUCCESS suppress (test). AC-7: KCXKVR +
6YG3HP regressions pass. AC-8: grep gates — 0 message-string classification, 0 HTTP200=success,
ShippingCore diff 0 (task này). AC-9: customer-facing binary rate availability giữ nguyên.
