# Evidence — TASK-W8SH0N: GHTK RATE error classification → CarrierRateOutcome v5

Date: 2026-09-14 · Basis: SPIKE-A1DGPY (official API semantics) + v5 contracts TASK-NAT3YV · 0 ShippingCore/VietNamAddress change

## A. Result: FULL RATE CLASSIFICATION ALIGNMENT

RATE path phân loại đầy đủ lên shared `CarrierRateOutcome` (v5 TASK-NAT3YV) — customer-facing
binary (rate / rate-unavailable) giữ nguyên; classification là orchestration-internal, sẵn sàng
cho E-C1/E-SL wiring.

## §39 Delta matrix

| Case | Before | After | Outcome |
|---|---|---|---|
| valid fee (success=true + amount + delivery=true) | rate (implicit success) | SUCCESS outcome + `CarrierRate(amount)` | **SUCCESS** |
| invalid address / business rejection (HTTP 200 success=false + error_code) | fee null → hide (mất distinction) | `unavailable(SERVICE_UNAVAILABLE)` + error_code diagnostic | **UNAVAILABLE** (no fallback) |
| delivery=false (unsupported destination) | hide | `unavailable(SERVICE_UNAVAILABLE)` | **UNAVAILABLE** |
| auth 403 (empty body) | CLIENT_ERROR → exception → hide | category CLIENT_ERROR → `unavailable(SERVICE_UNAVAILABLE)` non-transient (§7/§18) | **UNAVAILABLE** (no retry) |
| HTTP 5xx | SERVER_ERROR → exception (retry) → hide | `technicalFailure(TECHNICAL_ERROR)` — retry per policy vẫn chạy | **TECHNICAL_FAILURE** (fallback contributor) |
| timeout / network | NETWORK → exception (retry) → hide | `technicalFailure(TECHNICAL_ERROR)` | **TECHNICAL_FAILURE** |
| invalid JSON / malformed | INVALID_RESPONSE → hide | category INVALID_RESPONSE → `technicalFailure`; HTTP 200 missing/non-numeric fee → MALFORMED → `technicalFailure` | **TECHNICAL_FAILURE** |
| HTTP 200 success=false | **bị coi như malformed-fee → hide chung** (mất distinction) | `unavailable(SERVICE_UNAVAILABLE)` + error_code/message diagnostic | **UNAVAILABLE** — P0 classification fix |
| 429 | CLIENT_ERROR → hide | `unavailable(SERVICE_UNAVAILABLE)` conservative + NEEDS_PROVIDER_VERIFICATION | **UNAVAILABLE** (no retry) |
| success=true thiếu/non-numeric fee | float(null)=0.0 → 0-fee rate! | MALFORMED → TECHNICAL_FAILURE (§11 — không nhận 0-fee sentinel) | **TECHNICAL_FAILURE** — P0 fix phụ |

## Classification map (§15 ShippingFailureReason)

| Shape | Outcome | FailureReason |
|---|---|---|
| success=true + fee + delivery | SUCCESS | null |
| success=false / delivery=false | UNAVAILABLE | SERVICE_UNAVAILABLE |
| transport CLIENT_ERROR (400/403/404) + RATE_LIMIT (429) | UNAVAILABLE | SERVICE_UNAVAILABLE |
| transport NETWORK / SERVER_ERROR / TIMEOUT / INVALID_RESPONSE | TECHNICAL_FAILURE | TECHNICAL_ERROR |
| HTTP 200 MALFORMED | TECHNICAL_FAILURE | TECHNICAL_ERROR |

Raw GHTK error_code/message: chỉ vào log context + GhtkFeeResponse VO (carrier-owned) — 0 vào
ShippingCore (grep §37 CLEAN). Status = orchestration; reason = diagnostic (r1 rule giữ).

## Files

- NEW `Model/Rate/GhtkFeeResponse.php` — parsed-response VO (SUCCESS/BUSINESS_REJECTION/MALFORMED).
- NEW `Model/Rate/GhtkRateOutcomeFactory.php` — THE single classification point (§20).
- MOD `Model/Fee/FeeResponseMapper.php` — `parse()` thay `map()` (null-for-every-error removed, §26);
  success thiếu/non-numeric amount → MALFORMED (trừ delivery=false business answer).
- MOD `Model/GhtkApiException.php` — optional `category` (backward compat).
- MOD `Model/GhtkApiClient.php` — `wrap()` truyền shared category.
- MOD `Model/Carrier/Ghtk.php` — collect() consume factory; `logRateOutcome()` một dòng masked
  (status/reason/error_code/admin names — §24); customer-facing hide()/rate giữ nguyên (§27).

## Retry (§18) — unchanged

`RetryPolicy::safeRead` (NETWORK/SERVER_ERROR/TIMEOUT) — business rejection/403/429/invalid-JSON
no retry (category-driven, không message parsing). Client tests category/retry pass nguyên.

## Tests (§33–§35)

- `GhtkRateOutcomeFactoryTest` (15 — MỚI): §33 matrix đầy đủ + §35 real-aggregator integration
  (REAL `ServiceLevelRateAggregator` + seeded `ShippingServiceLevelRegistry`): business rejection
  → `hasTechnicalFailure=false` (never fallback); technical outage → `hasTechnicalFailure=true`;
  GHTK SUCCESS + carrier khác TECHNICAL → `hasSuccessfulRate=true` (suppress tại decision).
- `FeeResponseMapperTest` (8 — tái viết): success/rejection(+/- error_code)/malformed/delivery
  false-without-amount=business/missing-delivery-default-denied/extFees defaults.
- `GhtkApiClientTest` + `GhtkTest`: giữ pass (fixture raw arrays khớp parser; ctor += factory).

## Gates (§36/§37)

- Scoped (Ghtk|ShippingCore|VietNamAddress): **596 tests / 1595 assertions — 0 failure**.
- Full suite: 1506 tests — 10 failing TẤT CẢ pre-existing ngoài scope (Tracking 7,
  FulfillmentCore 3 — WIP streams; GHN đã tự-fix hết trong stream đó). New failures: 0.
- grep §37: 0 message-string classification; 0 raw-200 assumption; 0 GHTK error_code trong
  ShippingCore. ShippingCore/VietNamAddress diff trong task: 0 file.
- `php .ai/bin/project-ai-validate --check-specs --check-records` → exit 0.

## Unresolved (§I)

429 chính thức (NEEDS_PROVIDER_VERIFICATION — hiện conservative UNAVAILABLE) · aggregation wiring
vào checkout runtime (E-C1/E-SL composition — follow-up, ngoài scope) · CREATE classifier (không
đổi — §28; dùng chung parser mới khi làm ORDER_ID_EXIST recovery).
