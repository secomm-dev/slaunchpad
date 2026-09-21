# Secomm MoMo

MoMo wallet payment integration for Magento 2.4.x on **MoMo API v2**, rebuilt
as **payment-first order finalization** (MOMO-01): no Sales Order exists
before an authoritative, signature-verified MoMo success.

## Payment-first flow

1. **Initiate** — ACTIVE QUOTE + save method → reserve merchant reference
   (`order_ref`/`request_id`), freeze amount/currency/contract fingerprint
   into `secomm_momo_payment_attempt` (status `initiated` → `active`) →
   MoMo `create` → redirect to `payUrl`. **No Sales Order.**
2. **Verify** — every callback is verified server-side: HMAC signature +
   partner/order/request identity + frozen amount. Return additionally
   re-queries `v2/query` (browser params never trusted; MoMo signs the query
   REQUEST — the unsigned response is identity-checked by its echoes against
   the exact request just sent). Verified success moves the attempt to
   `paid` (money-real).
3. **Finalize** — canonical `OrderFinalizer`: attempt row `FOR UPDATE` →
   single-use placement grant (enforced by a `QuoteManagement::placeOrder`
   plugin) → exactly one order → invoice/capture per `payment_action` →
   attempt bound to order → email (claimed once). Duplicates recover the
   bound order.
4. **Recover** — if finalization fails after `paid`, the attempt stays
   money-real and the IPN answers HTTP 500 so MoMo's IPN retries act as the
   recovery driver. Contract/amount/identity anomalies quarantine the attempt
   (`requires_reconciliation` + typed code) — never silent cancellation.

## Architecture

Built on `Magento\Payment\Model\Method\Adapter` (virtualType `MoMoFacade`) with a Gateway
CommandPool — **not** the deprecated `AbstractMethod`. Mirrors `Secomm_ZaloPay`.

- **Create order** → POST `/v2/gateway/api/create` → redirect browser to returned `payUrl`.
- **Return** (`momo/payment/returnaction`, GET) — UX/recovery only; resolves the attempt
  by the `orderId` echo (= `order_ref`), re-verifies via `v2/query` (non-final
  `1000`/`7000`/`7002` and ambiguous request/system/unknown codes never mutate —
  MOMO-04), finalizes, then
  rebuilds the 5 checkout success-session keys (like core `Onepage::saveOrder`).
- **Notify / IPN** (`momo/payment/notify`, POST) — **authoritative**; strict signed-value
  parsing + 13-field signature + identity/amount echo checks against the attempt.
- **Refund** (MOMO-02) — admin creditmemo → native `CreditmemoService::refund()` →
  POST `/v2/gateway/api/refund`, made idempotent and uncertainty-safe (below).

## Refund (MOMO-02): idempotent + uncertainty-safe

Credit Memo refunds stay **native Magento accounting** (Invoice → Credit Memo →
Refund; no custom refund button, no custom controller touching order state).
Secomm_MoMo owns only the provider-side request identity, response
classification and reconciliation evidence:

- **Durable identity** — every refund mints its own `refund_order_id` (`-RF`)
  and `requestId` (`-RQ`, provider idempotency key, valid ≥31 days) and is
  persisted as a `secomm_momo_refund` row (order/creditmemo/invoice linkage,
  amount, original `transId`, classification, timestamps) on an **independent
  DB connection**, so evidence survives the `CreditmemoService` rollback.
- **Duplicate protection** — a UNIQUE index on
  `(momo_order_ref, momo_trans_id, open_flag)` allows at most ONE open refund
  per payment: a second creditmemo is blocked before any provider call
  (pending and unknown rows both block). Terminal rows release the slot, so
  sequential partial refunds and retry-after-FAILED stay native.
- **Classification contract** (verified against developers.momo.vn, the refund
  response carries NO signature) — SUCCESS only on intact echoes of the exact
  request (requestId/orderId/amount with a strict integer grammar,
  partnerCode conflict-intolerant) + `resultCode == 0` + a valid positive
  refund `transId`; non-final codes (Final Status = No: 10/11/12/13,
  20/21/22, 40/41/42/43/45/47, 1000, 7000, 7002, 9000) → UNKNOWN
  (`provider_processing`), never FAILED; provider-confirmed final failures →
  FAILED; malformed/echo-mismatch/transport → UNKNOWN. The query-based
  resolve path binds the response to the EXACT query sent (top-level
  requestId/orderId echoes verified, partnerCode conflict-intolerant) and
  requires an exact `refundTrans[].orderId` match — ambiguity never
  resolves a terminal verdict — and each resolve invocation signs the
  query with its own fresh `requestId` (the stored refund `requestId` stays
  immutable submission evidence).
- **UNKNOWN is never blindly retried** — FAILED/UNKNOWN throw after the
  outcome is recorded, so the native creditmemo rolls back and no accounting
  is finalized on an unconfirmed outcome.
- **Budget drift guard** — provider-confirmed refunds exceeding the
  accounting `amount_refunded` (e.g. an UNKNOWN resolved to SUCCESS after its
  creditmemo rolled back) block further refunds until the books are aligned.

### Operator runbook

```bash
# Inspect refund evidence (filterable by status/order)
bin/magento momo:refund:list --status unknown

# Resolve an unconfirmed row by querying the provider (query-only; the
# refund itself is NEVER re-posted with a new identity)
bin/magento momo:refund:resolve <requestId>
```

Resolve outcomes: `SUCCESS` (issue an **offline credit memo** for the amount
if Magento still shows it unrefunded), `FAILED` (slot released — refund again
normally), `UNKNOWN` (leave open, re-query later or contact MoMo support).

## Lost-IPN recovery (MOMO-03): bounded proactive reconciliation

If MoMo accepted the money but the authoritative IPN is lost or delayed, the
attempt row stays unpaid-looking while the customer's wallet was charged.
Cron `secomm_momo_payment_recovery_cronjob` (`Secomm\MoMo\Cron\
PaymentRecoveryCronjob`, every 5 minutes, group `default`) runs a **bounded**
recovery pass (`Service\PaymentRecovery`):

- **Selection** — only `active`/`paid` attempts with NO bound order, not
  quarantined, older than the callback window (`recovery_window`, default
  15 min), under the per-row query budget, oldest first, max
  `recovery_batch_size` rows per pass (default 25).
- **Claim before HTTP** — one atomic conditional UPDATE per row increments
  `recovery_attempts` (and flips `recovery_exhausted` on the last permitted
  query, default budget `recovery_max_attempts` = 5). A lost race (IPN/Return
  beat the cron) skips the row; no DB lock is held across the MoMo HTTP call.
- **Verification** — the authoritative `v2/query` runs with the attempt's
  ORIGINAL identity (`order_ref` as MoMo orderId; fresh query requestId per
  MoMo contract), outside any transaction.
- **Outcomes** — always through the SAME canonical services as IPN/Return,
  never a second order-placement implementation. Result codes classify ONLY
  through the shared fail-safe purchase-query classifier
  (`Service\PurchaseQueryClassifier`, MOMO-04 — the SAME explicit,
  provider-documented allowlists as the browser Return path;
  developers.momo.vn result-code table, verified 2026-09-21) — an unknown
  code NEVER defaults to FAILED:
  - paid code (`0`, or `9000` — authorized — for the module's 1-step
    `captureWallet`/default autoCapture contract) + exact amount + positive
    `transId` → `PaymentAttemptLifecycle::recordVerifiedPaid` →
    `OrderFinalizer::finalizeOrRecover` (exactly one order);
  - paid + wrong amount → `recordAmountMismatch` (quarantine, no order);
  - paid + missing/bad `transId` → `recordProviderIdentityUnavailable`
    (quarantine, no order);
  - `1000`/`7000`/`7002` (non-final) → pending, no mutation;
  - documented FINAL failures (`98`, `99`, `1001`–`1007`, `1017`, `1026`,
    `2019`, `4001`, `4002`, `4100`) → `recordVerifiedFailure` (PAID/FINALIZED
    are never regressed);
  - request/system non-final codes (`10`–`13`, `20`–`22`, `40`–`43`, `45`,
    `47`), any unmapped code, transport failure or unparseable `resultCode`
    → **AMBIGUOUS**: logged, no mutation, never a false failure; retried on
    a later pass (budget permitting).
- **Exhaustion is explicit** — the row gets the machine-readable
  `recovery_exhausted` marker + a critical log; it is never selected again.
  The marker is OPERATIONAL ONLY: it is not money evidence and never
  quarantines the attempt — a valid authenticated IPN/Return arriving later
  still resolves the payment normally.

Config defaults (`payment/momo_payment/recovery_*`): `recovery_window` 15,
`recovery_batch_size` 25, `recovery_max_attempts` 5.

## Config (Admin → Sales → Payment Methods → MoMo)

| Field | Notes |
|-------|-------|
| Partner Code | MoMo partner code |
| Access Key | stored encrypted |
| Secret Key | HMAC signing key, stored encrypted |
| Sandbox Mode | `test-payment.momo.vn` vs `payment.momo.vn` |
| Return URL | public URL → `.../momo/payment/returnaction` |
| Notify URL | public URL → `.../momo/payment/notify` (IPN) |
| Payment Action | canonical Magento key `payment/momo_payment/payment_action` (MOMO-05), default `authorize_capture` → local capture after verification; any other value finalizes without capture |

> Legacy note (MOMO-05): installations that saved a value under the pre-alignment
> admin field key `payment/momo_payment/momo_payment_action` are unaffected —
> that key never had a runtime reader (behavior was always the
> `authorize_capture` default). The orphaned row is inert and may be deleted;
> re-saving via the Payment Action field now writes the canonical key.

**Signature**: `hmac_sha256(rawSignature, secretKey)`, where
`rawSignature = accessKey=...&amount=...&extraData=...&ipnUrl=...&orderId=...&orderInfo=...&partnerCode=...&redirectUrl=...&requestId=...&requestType=...`.

## Currency

MoMo settles in VND. The method is only available for VND quotes.

## Gotchas

- MoMo only knows `partnerCode/accessKey/secretKey` + return/notify URLs — the Magento method
  code (`momo_payment`) is internal and must NOT be changed.
- The Notify (IPN) is authoritative; the Return action is lenient on purpose.
- Amount range: 1.000đ – 20.000.000đ.

## Verify

```bash
bin/magento module:enable Secomm_MoMo
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```
