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
  by the `orderId` echo (= `order_ref`), re-verifies via `v2/query` (7000/7002 stay
  non-terminal), finalizes, then
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
  request (requestId/orderId/amount, partnerCode conflict-intolerant) +
  `resultCode == 0`; `7002` = still processing → UNKNOWN, never FAILED; any
  other code → FAILED; malformed/echo-mismatch/transport → UNKNOWN.
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

## Config (Admin → Sales → Payment Methods → MoMo)

| Field | Notes |
|-------|-------|
| Partner Code | MoMo partner code |
| Access Key | stored encrypted |
| Secret Key | HMAC signing key, stored encrypted |
| Sandbox Mode | `test-payment.momo.vn` vs `payment.momo.vn` |
| Return URL | public URL → `.../momo/payment/returnaction` |
| Notify URL | public URL → `.../momo/payment/notify` (IPN) |

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
