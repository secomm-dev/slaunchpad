# ZaloPay provider contract evidence — corrective round 3 (TASK-EDS9T5)

Recorded: 2026-09-10. Every design decision in round 3 is anchored to the
OFFICIAL ZaloPay documents below. API generation: **ZaloPay v2**
(`https://gateway/v2/...` — the generation this module already uses for
`/v2/create` and `/v2/query`; callback `type` field: 1 = Order, 2 = Agreement;
this module implements Order callbacks only).

## 1. Callback (IPN) — request schema

Source: https://docs.zalopay.vn/docs/specs/callback-api/
(official spec) — provider calls the merchant endpoint with:

- **Method: POST** (hence POST-only controller; `HttpGetActionInterface`
  removed in round 3).
- **Body (JSON)**: `{ "data": "<json-string>", "mac": "<hex>", "type": 1 }`
  - `data` — a JSON **string** with:
    `app_id`, `app_trans_id`, `app_time`, `app_user`,
    **`amount` (long, VND — "Amount received")**, `embed_data`, `item`,
    **`zp_trans_id` (long)**, `server_time`, `channel`, `merchant_user_id`,
    `user_fee_amount`, `discount_amount`.
  - `mac` = HMAC-SHA256(`data`, **key2**).

Consequence (Blocker 4): the callback carries `amount` on the wire, so a
missing or zero amount is an anomaly that MUST NOT be interpreted as
"continue anyway" — the authoritative fallback query is mandatory
(`IpnProcessor::verifyByQuery()`).

## 2. Callback — acknowledgement schema (Blocker 3)

Source: https://docs.zalopay.vn/docs/specs/callback-api/ + official
knowledge base "Callback":
https://docs.zalopay.vn/docs/developer-tools/knowledge-base/callback/

- Merchant MUST answer **HTTP 200** with JSON
  **`{ "return_code": <int>, "return_message": <string> }`**.
  - `return_code 1` = "Success"
  - `return_code 2` = "Invalid"
  - official KB sample additionally uses `return_code 0` = "callback again
    (up to 3 times)" for transient failures
    (sample message: "Temporary failure, please retry.").
- **No `mac` field in the response. No `errors`/`messages` fields.**
  The legacy `{errors, messages}` body with HTTP 404/500 in this module was
  NOT the provider protocol and is replaced (controller `Ipn.php`).

## 3. Callback — retry semantics

Source: https://docs.zalopay.vn/docs/developer-tools/knowledge-base/callback/

- ZaloPay retries the callback **up to 3 times** when the merchant does not
  answer `return_code 1`.
- Official recommendations in the same page: idempotent handling, HTTPS,
  exclude the callback endpoint from CSRF (implemented via
  `CsrfAwareActionInterface::validateForCsrf()`), and — quoted — after
  **15 minutes** from order establishment without a callback, the merchant
  should proactively call QueryOrder.

## 4. Order status (v2/query) — return codes and fields (Blockers 1/4/6)

Source: https://docs.zalopay.vn/docs/specs/order-query/

- Request: `app_id`, `app_trans_id`, `mac` = HMAC-SHA256(`app_id|app_trans_id|key1`, key1)
  — server-to-server; the ONLY decision input is `app_trans_id` (basis for
  Blocker 1: browser checksum cannot and must not gate this query).
- Response fields: `return_code`, `return_message`, `sub_return_code`,
  `sub_return_message`, `is_processing`,
  **`amount` (int64 — "only available when the payment is successful")**,
  `zp_trans_id` (int64), `server_time`, `discount_amount`.
- `return_code`: **1 = SUCCESS, 2 = FAIL, 3 = PROCESSING**
  (status-code page: https://docs.zalopay.vn/docs/order-status/ ;
  e.g. sub codes -101 `ORDER_NOT_EXIST`, -54 `TIME_INVALID`).
- `amount` availability note is why a paid-but-amountless query result
  quarantines (`amount_unavailable`) instead of finalizing (Blocker 4).

## 5. Lost-callback recovery interval (Blocker 6)

Source: https://docs.zalopay.vn/docs/developer-tools/knowledge-base/callback/
("Retry" section):

> After 15 minutes from the time of the order establishment, if you still do
> not receive a callback from ZaloPay, the merchant needs to call QueryOrder
> API proactively to get the final result.

Implemented as cron `secomm_zalopay_payment_recovery_cronjob` (every 5
minutes) + `Service/PaymentRecovery` with configurable
`payment/zalopay/recovery_window` (default 15 min = the documented
interval), `recovery_batch_size` (25) and `recovery_max_attempts` (5).

## Usage map (production code)

| Contract fact | Implementation |
|---|---|
| POST callback, `{data,mac,type}` | `Controller/Payment/Ipn.php` |
| `amount` present in callback data | `IpnProcessor::process()` — strict-amount rule |
| `{return_code, return_message}` ack, HTTP 200 | `Ipn::RESPONSE_BY_OUTCOME` |
| retry ≤ 3 / return_code 0 | `OUTCOME_RETRYABLE_FAILURE` mapping |
| v2/query 1/2/3 + amount-only-on-success | `IpnProcessor::verifyByQuery()`, `ReturnProcessor`, `PaymentRecovery` |
| 15-minute proactive query | `PaymentRecovery` + crontab.xml |
