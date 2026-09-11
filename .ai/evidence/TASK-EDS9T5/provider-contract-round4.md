# ZaloPay provider contract evidence — corrective round 4 (TASK-EDS9T5)

Recorded: 2026-09-10 (round-4 implementation session). Sources re-fetched
verbatim this session via the official docs. Round-3 evidence
(`provider-contract-round3.md`) remains valid; this file adds the fields
round 4 depends on: **callback `type`**, **`app_id` semantics**, the
**MAC-binding fact**, **`zp_trans_id` semantics** and **QueryOrder provider
identity**. API generation: **ZaloPay v2**.

## 1. Callback envelope — the `type` field (Blocker 1a)

Source: https://docs.zalopay.vn/docs/specs/callback-api/ (re-fetched
2026-09-10, verbatim):

- Provider → merchant: **POST**, JSON body
  **`{ "data": "<json-string>", "mac": "<hex>", "type": <int> }`**.
- `type` is a TOP-LEVEL envelope field (OUTSIDE the signed `data`
  string): **`1` = Order callback, `2` = Agreement callback**.
- The official data sample carries `"type": 1` for an order payment.
- Consequence: this module implements ORDER payments only, so a callback
  whose `type` is missing or ≠ 1 is NOT an order-payment notification —
  it must never mutate an attempt, never mark PAID, never place an order.
  The provider-appropriate acknowledgement for an un-processable callback
  is return_code 2 "Invalid" (§3). (Implemented: `IpnProcessor::process()`
  type gate.)

## 2. MAC binding — the WHOLE `data` string is signed (Blocker 1d)

Source: https://docs.zalopay.vn/docs/specs/callback-api/ (verbatim):

- `mac = HMAC(hmac_algorihtm [default HmacSHA256], callback key [key2],
  hmacinput = data)` — the MAC input is the **ENTIRE `data` string**.
- `key2` is the merchant's secret callback key provided by ZaloPay at
  registration.
- Consequence: EVERY field inside `data` — including **`app_id`**,
  `app_trans_id`, `amount`, `zp_trans_id` — is cryptographically
  authenticated: a payload with a valid MAC was necessarily produced by
  ZaloPay (or a holder of key2). A MAC-valid `app_id` therefore cannot be
  forged by a third party; a mismatch against the store's configured
  `app_id` can only be a CONFIGURATION/ENVIRONMENT error (callback for a
  different ZaloPay application landing on this endpoint), not a forgery.
  Design (DEC-TASKEDS9T5-004): the MAC contract already guarantees
  `app_id` authenticity; the module ADDITIONALLY compares
  `data.app_id` against the configured `app_id` defensively — mismatch →
  return_code 2 "Invalid" + critical log + ZERO mutation (a configuration
  error must not poison payment state), and the attempt stays available
  to the recovery worker / a later correctly-configured callback.

## 3. `app_id` semantics

- Callback `data.app_id`: int, "Order's app_id"
  (https://docs.zalopay.vn/docs/specs/callback-api/).
- Query `app_id`: "This is a Zalopay-provided identifier specific to the
  merchant's service or application, established during the integration
  agreement for payment methods"
  (https://docs.zalopay.vn/docs/specs/order-query/).
- Both identify the merchant application the transaction belongs to; the
  module's configured `app_id` (system config, `AbstractDataBuilder::APP_ID`)
  is the one its transactions are created under.

## 4. `zp_trans_id` semantics (Blockers 1b/1c)

- Callback `data.zp_trans_id`: long, **"Zalopay's transaction code"**
  (https://docs.zalopay.vn/docs/specs/callback-api/). Official sample:
  `200904000000389` — a positive integer.
- Query `zp_trans_id`: int64, **"Zalopay's transaction code, initiate
  when users confirms payment at Zalopay site. Merchant uses this to
  request refund & reconciliation"**
  (https://docs.zalopay.vn/docs/specs/order-query/ — re-fetched
  2026-09-10, verbatim).
- The docs do NOT guarantee `zp_trans_id` on every query response (only
  `amount` is explicitly conditioned on success), but its documented
  meaning is "the provider transaction created when the user confirmed
  payment" — i.e. the authoritative provider identity of the money.
- Consequences:
  - Automatic finalization requires a POSITIVE `zp_trans_id` (> 0):
    missing / 0 / malformed in the callback → the authoritative
    v2/query must prove return_code 1 + exact amount + positive
    `zp_trans_id` before any PAID/order.
  - The query result's `zp_trans_id` is the authoritative identity for
    reconciliation/refund — a DIFFERENT positive id from the callback's
    on the same app_trans_id is a provider identity conflict:
    `requires_reconciliation` + `provider_transaction_conflict`, BOTH
    identities preserved in evidence, never auto-finalized.
  - A malformed (non-numeric) signed value is NOT cast to zero to sneak
    into the "missing → query fallback" branch: malformed signed payload
    → return_code 2 "Invalid", zero mutation (the recovery worker
    converges the money via v2/query).

## 5. Callback acknowledgement schema (unchanged from round 3, re-verified)

Source: https://docs.zalopay.vn/docs/specs/callback-api/ +
https://docs.zalopay.vn/docs/developer-tools/knowledge-base/callback/

- Merchant MUST answer **HTTP 200** JSON
  `{ "return_code": <int>, "return_message": <string> }`:
  `1` = "Success", `2` = "Invalid", official KB sample `0` =
  "callback again (up to 3 times)" (e.g. "Temporary failure, please
  retry."). Mapping: `OUTCOME_SUCCESS`/`OUTCOME_ACK_RECONCILIATION` → 1,
  `OUTCOME_INVALID_CALLBACK` → 2, `OUTCOME_RETRYABLE_FAILURE` → 0
  (`Controller/Payment/Ipn.php::RESPONSE_BY_OUTCOME`).

## 6. QueryOrder (v2/query) — provider identity + amount (re-verified verbatim)

Source: https://docs.zalopay.vn/docs/specs/order-query/ (re-fetched
2026-09-10):

- Request: `app_id`, `app_trans_id`, `mac = HMAC(HmacSHA256, key1,
  app_id + "|" + app_trans_id + "|" + key1)` — server-to-server; the
  ONLY decision input is `app_trans_id`.
- Response: `return_code`, `return_message`, `sub_return_code`,
  `sub_return_message`, `is_processing`, `amount` (int64 — "Amount
  received (only available when the payment is successful)"),
  **`zp_trans_id`** (int64 — see §4), `server_time`, `discount_amount`.
- Recommended timing (verbatim): "Use a cron job or scheduled task to
  periodically query the order status until a callback is received or 15
  minutes (the default order expiration time) have passed" / "Perform a
  one-time query 15 minutes after the order is created if no callback
  has been received." — basis of the recovery worker.
- `return_code` 1 = SUCCESS / 2 = FAIL / 3 = PROCESSING: verified in
  round 3 at https://docs.zalopay.vn/docs/order-status/ (the page is
  JS-rendered and did not re-render this session; the round-3 evidence
  stands — `provider-contract-round3.md` §4).

## Usage map (round-4 production code)

| Contract fact | Implementation |
|---|---|
| `type` envelope field, 1 = Order | `IpnProcessor::process()` type gate (≠1 → INVALID, zero mutation) |
| MAC over ENTIRE data string (key2) | `IpnProcessor::process()` MAC gate (unchanged); `app_id` authenticity argument (DEC-TASKEDS9T5-004) |
| `data.app_id` vs configured app_id | `Authorization::getAppId()` + `IpnProcessor` defensive gate (mismatch → INVALID, zero mutation) |
| `zp_trans_id` positive = provider identity | `IpnProcessor` strict id parsing; query MUST prove positive id before PAID |
| callback id ≠ query id → conflict | `IpnProcessor::verifyByQuery()` → `PaymentAttemptLifecycle::recordProviderIdentityConflict()` (both ids preserved) |
| no provable positive id | `PaymentAttemptLifecycle::recordProviderIdentityUnavailable()` (money-real quarantine) |
| query response amount/id semantics | `IpnProcessor::verifyByQuery()`, `ReturnProcessor`, `PaymentRecovery` (unchanged) |
| HTTP 200 `{return_code, return_message}` | `Ipn::RESPONSE_BY_OUTCOME` (unchanged) |
