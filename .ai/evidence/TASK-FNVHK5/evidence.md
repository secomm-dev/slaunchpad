# Evidence — TASK-FNVHK5: GHTK CANCEL lifecycle

Date: 2026-09-14 · Basis: SPIKE-A1DGPY §12 (official api-cancel-order) · 0 ShippingCore/VietNamAddress change · KHÔNG auto-wire Magento cancel (§19)

## A. Result: FULL CANCEL LIFECYCLE ALIGNMENT (runtime method/message shape NEEDS_RUNTIME_VERIFICATION)

## §41 Delta matrix

| Case | Before | After |
|---|---|---|
| cancel supported | **no** (grep: 0 cancel implementation) | **yes** — `GhtkApiClient::cancelShipment` + `CancelShipmentService` |
| normal success | n/a | **CANCELLED** (+log_id) |
| already cancelled (success=false + documented message) | no handling | **ALREADY_CANCELLED — benign satisfied** (`isSatisfied()=true`, không exception) |
| invalid state ("Đơn đã lấy hàng, không thể hủy đơn.") | n/a | **BUSINESS_REJECTION** no-retry |
| 403 auth / 400 invalid / unknown shipment | n/a | **BUSINESS_REJECTION** (CLIENT_ERROR category) |
| timeout / network / 5xx / invalid JSON | n/a | **TECHNICAL_FAILURE** |
| automatic retry | n/a | **none** — single attempt (mutation; không dùng safeRead) |

## B. Official contract + implementation choices

- Endpoint: `POST /services/shipment/cancel/{rawurlencode(identifier)}` — Endpoint section = POST,
  samples = GET (**discrepancy documented**; POST = formal contract; method flip = 1 dòng client —
  NEEDS_RUNTIME_VERIFICATION TASK-44F7V7).
- Identifier: GHTK label (primary — từ CREATE identity `OrderSubmitResult.labelId`) hoặc
  `partner_id:{code}`; non-empty precondition (LocalizedException); rawurlencode tại client.
- Allowed provider states: 1/2/12; sau đó success=false + message — phân loại qua documented
  message contract (GHTK cancel response KHÔNG có error_code field — khác RATE/CREATE):
  exact-message match (whitespace/case-normalized) cho ALREADY_CANCELLED; unmatched variant →
  BUSINESS_REJECTION fail-closed.
- Response `{success, message, log_id}` — log_id surface cho support.

## C/D/E. Typed model + semantics

`GhtkCancelResponse` 4 kinds + `isSatisfied()` (= CANCELLED hoặc ALREADY_CANCELLED — intent
satisfied). BUSINESS vs TECHNICAL: transport category split (CLIENT_ERROR/RATE_LIMIT → business;
NETWORK/SERVER_ERROR/TIMEOUT/INVALID_RESPONSE → technical) — cùng principle RATE/CREATE.
Address handoff/CarrierRateOutcome: 0 (grep §39 CLEAN).

## F. Retry / idempotency (§13/§14)

Single automatic attempt; manual retry test: attempt 1 TIMEOUT (TECHNICAL) → attempt 2
already-cancelled (ALREADY_CANCELLED, satisfied) — client đúng 1 call/invocation.

## G. Magento integration boundary (§18/§19/§20)

`CancelShipmentService::cancel(identifier): GhtkCancelResponse` — caller expectation documented
trên class: KHÔNG mutate Magento order/shipment/payment/refund; KHÔNG wired vào Magento
order-cancel flow (observer/plugin = follow-up OrderOperations/bridge riêng). Tracking sau cancel
chạy tự nhiên qua webhook/pipeline (status -1 → CANCELLED qua GhtkStatusMapper — §21; không fake
tracking events). Identifier source: `OrderSubmitResult->labelId` từ CREATE (BE5YD2) — đủ cho
cancel, không có gap.

## Tests (§31–§37)

`CancelShipmentServiceTest` (14 — MỚI): success CANCELLED + log_id; already-cancelled benign;
state rejection; 400/403/unknown → BUSINESS; RATE_LIMIT conservative BUSINESS; NETWORK/SERVER/
TIMEOUT/INVALID_JSON → TECHNICAL; manual-retry benign resolution; empty identifier rejected
(không gọi API); partner_id variant accepted; single-call-per-invocation.
Scoped (Ghtk|ShippingCore|VietNamAddress): **620 tests / 1663 assertions — 0 failure**.
Full suite: 1530 tests — 10 failing pre-existing ngoài scope (Tracking 7 + FulfillmentCore 3).
New failures: 0. Validator: exit 0.

## NEEDS_RUNTIME_VERIFICATION

POST vs GET method · exact already-cancelled message wording · response envelope (log_id) —
probe theo runbook TASK-44F7V7 (cancel probe: chỉ sau khi có token; staging cleanup dùng chính
cancel này).
