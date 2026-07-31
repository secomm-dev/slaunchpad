# Secomm MoMo

MoMo wallet payment integration for Magento 2.4.x on **MoMo API v2** (redirect + IPN + refund).

## Architecture

Built on `Magento\Payment\Model\Method\Adapter` (virtualType `MoMoFacade`) with a Gateway
CommandPool — **not** the deprecated `AbstractMethod`. Mirrors `Secomm_ZaloPay`.

- **Create order** → POST `/v2/gateway/api/create` → redirect browser to returned `payUrl`.
- **Return** (`momo/payment/returnaction`, GET) — browser redirect back; lenient.
- **Notify / IPN** (`momo/payment/notify`, POST) — **authoritative**; verifies MoMo signature,
  creates invoice, sets order to `processing`.
- **Refund** — admin creditmemo → POST `/v2/gateway/api/refund`.

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
