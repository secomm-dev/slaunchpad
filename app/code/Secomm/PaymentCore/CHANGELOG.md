# Changelog

## 1.0.0 — 2026-08-25 (FEAT-CSWYEJ, TASK-NJ77PG)

- Initial scaffold: module registration, config tree (`secomm_paymentcore/general|cron`), ACL, logger channel `secomm_paymentcore.log`, declarative schema `secomm_paymentcore_payment` (unique order_id, index status+expires_at), Api/Data + repository, adapter contract (`PaymentProviderAdapterInterface` + `VerifyResult` + `AdapterPool`), i18n vi_VN/en_US.

## 1.0.1 — 2026-08-25 (FEAT-CSWYEJ, DEC-FEATCSWYEJ-002)

- Adapter placement revised: VNPAY adapter (`Model/Provider/VnpayAdapter` + `VnpayCheckoutUrl`) moved INSIDE Secomm_PaymentCore; all `Vnpayment_VNPAY` changes reverted (extension pristine). `querydr_url` becomes a Payment Core config (`secomm_paymentcore/vnpay/querydr_url`); empty value keeps auto-cancel off (UNKNOWN → skip).

## 1.1.0 — 2026-08-25 (TASK-Q2BAHW)

- Console command `bin/magento paymentcore:expire:run` for QC: same logic as the cron job, with `--dry-run` and optional `order-id` filter. `ExpirePayments::execute()` gains an optional order filter (cron behaviour unchanged).

## 1.2.0 — 2026-08-25 (DEC-FEATCSWYEJ-004)

- Cron simplification: expired + order still pending = not paid → cancel directly (per-order lock + state reload remain). Removed the querydr pre-cancel verification path entirely: `VerifyResult`, `PaymentProviderAdapterInterface::isPaymentCompleted()`, `VnpayAdapter` querydr call + `vnpay_initiated_at` tracking, `secomm_paymentcore/vnpay/*` config (system.xml group, config.xml default, i18n entries).
