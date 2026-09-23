# Evidence — TASK-BE5YD2: GHTK CREATE lifecycle reliability

Date: 2026-09-14 · Basis: SPIKE-A1DGPY §2 (official CREATE docs + ORDER_ID_EXIST) · DEC-TASKBE5YD2-001 · 0 ShippingCore/VietNamAddress change

## A. Result: FULL CREATE RELIABILITY ALIGNMENT (runtime shape NEEDS_RUNTIME_VERIFICATION documented)

## §39 Delta matrix

| Case | Before | After |
|---|---|---|
| normal success | success → label/tracking reads | **CREATED** (normalized identity: partnerId/label/tracking/status; tracking_id precedence) |
| ORDER_ID_EXIST matching partner_id + ghtk_label | generic rejection → dead end | **RECOVERED_EXISTING** (reuse provider identity; result.recovered=true; comment "existing order recovered"; KHÔNG submit lại, KHÔNG shipment thứ hai) |
| ORDER_ID_EXIST mismatched partner_id | generic failure | **hard conflict** ("different reference" — LocalizedException) |
| ORDER_ID_EXIST missing partner_id | generic failure | **hard failure** ("did not identify it" — never silent recovery) |
| ORDER_ID_EXIST missing label identity | generic failure | **hard failure** + runtime-verification note (TASK-44F7V7) |
| business rejection (error_code khác) | generic failure | **typed BUSINESS_REJECTION** (error_code/message diagnostic) |
| 403 (CLIENT_ERROR category) | generic exception | **business/config failure** non-retry |
| 400 | generic exception | **business rejection** non-retry |
| timeout / network / INVALID_RESPONSE | exception → generic message | **TECHNICAL failure** + safe-retry guidance message |
| 5xx | exception → generic message | **TECHNICAL failure** non-retry |
| malformed (missing success contract / success=true thiếu identity) | partial handling | **MALFORMED → technical failure** — không tạo shipment với identity giả |

## B. CREATE response model

`Model/OrderSubmit/GhtkCreateResponse` (typed VO, 4 kinds + identity normalization §19):
CREATED(partnerId, label, tracking, providerStatus) · DUPLICATE_EXISTING(same shape, error_code=ORDER_ID_EXIST) · BUSINESS_REJECTION(errorCode, message) · MALFORMED. Identity normalization: success → partner_id/label||label_id/tracking_id→tracking_code→tracking→label; duplicate → partner_id/ghtk_label||label||label_id (ghtk_label primary per §20)/status. `hasUsableIdentity()` guard.

## C/D/E. Service lifecycle + recovery + manual retry

`OrderSubmitService::submit()`: transport GhtkApiException → category split (CLIENT_ERROR/RATE_LIMIT → BUSINESS message; NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → TECHNICAL message với hướng dẫn "retrying is safe: the same order reference will recover") → parse → MALFORMED → technical abort → BUSINESS_REJECTION → abort với reason → DUPLICATE_EXISTING → `validateDuplicate()` (partner_id === order.id + hasUsableIdentity, else hard conflict) → OrderSubmitResult(recovered flag) + comment phân biệt "submitted"/"recovered".

**Deterministic id = idempotency**: `buildPartnerOrderId` KHÔNG đổi — failed submit không tăng shipment count → retry dùng lại `ghtk-{increment}-{seq}` → hit recovery path (§12). Single attempt giữ: `RetryPolicy::singleAttempt` (grep gate — no POST retry loop).

## G. Native Magento shipment behavior

`requestToShipment → OrderSubmitService → GHTK` không đổi; failure (mọi kind) → LocalizedException → LabelGenerator aborts shipment save (no false success). Recovery chỉ hành xử như creation thành công SAU khi identity validation pass. Test §33 chứng minh: attempt 1 technical abort (no shipment), attempt 2 same id → recovered result — captured ids identical `['ghtk-100000001-1','ghtk-100000001-1']`.

## Logging (§23/§24)

CREATE logs: order_id (Secomm partner id), classification BUSINESS/TECHNICAL, error_code, reason (truncate), recovered. Không token/telephone/full address/raw payload. Test fixtures dùng fake identities ("Probe Receiver" style).

## Gates (§37)

```
1. POST retry loop: CLEAN — RetryPolicy::singleAttempt duy nhất tại GhtkApiClient:100
2. ORDER_ID_EXIST generic hard reject: CLEAN — recovery path mới (refs còn lại = docblocks +
   recovery/logging; OrderRequestMapper docblock đã cập nhật)
3. CarrierRateOutcome trong Model/OrderSubmit*: CLEAN (duy nhất docblock note "deliberately do NOT apply")
4. ShippingCore/VietNamAddress: 0 file mới/sửa bởi task này (git status entries = pre-existing r0/streams)
```

## Tests (§31–§36)

- `OrderResponseMapperTest` rewrite (9): CREATED fields + tracking_id precedence + label fallback
  + numeric cast; DUPLICATE top-level + order-block positions; BUSINESS; MALFORMED (×3).
- `OrderSubmitServiceTest` +6 scenarios: recovered flow (recovered=true + comment + providerStatus),
  mismatch hard conflict, missing partner_id, missing label identity, 403 business non-retry,
  5xx technical non-retry, malformed technical, **manual-retry recovery** (2 attempts, same id).
- Scoped (Ghtk|ShippingCore|VietNamAddress): **606 tests / 1630 assertions — 0 failure**.
- Full suite: 1516 tests — 10 failing TẤT CẢ pre-existing ngoài scope (Tracking 7 + FulfillmentCore 3).
  New failures: 0.
- Validator: exit 0.

## NEEDS_RUNTIME_VERIFICATION (TASK-44F7V7 follow-up)

Exact ORDER_ID_EXIST payload positions (top-level vs order block) + field names (error_code exact
string, ghtk_label) — parser defensive cả hai vị trí; sai thật → hard failure fail-closed.
