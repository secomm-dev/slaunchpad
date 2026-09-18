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
   re-queries `v2/query` (browser params never trusted). Verified success
   moves the attempt to `paid` (money-real).
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
  by the `orderId` echo (= `order_ref`), re-verifies via `v2/query`, finalizes, then
  rebuilds the 5 checkout success-session keys (like core `Onepage::saveOrder`).
- **Notify / IPN** (`momo/payment/notify`, POST) — **authoritative**; strict signed-value
  parsing + 13-field signature + identity/amount echo checks against the attempt.
- **Refund** — admin creditmemo → POST `/v2/gateway/api/refund` (unchanged).

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
