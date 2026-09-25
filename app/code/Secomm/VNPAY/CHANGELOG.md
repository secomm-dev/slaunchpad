# Secomm_VNPAY — Changelog

## [Unreleased]

### Fixed
- **Online Refund button unreachable**: the invoice created by the IPN had
  no `transaction_id`, so the credit memo creation page always showed only
  "Refund Offline" (core gate `Creditmemo\Create\Items`:
  `$creditmemo->getInvoice()->getTransactionId()`). The IPN now binds
  `vnp_TransactionNo` to the invoice before `register()`. Refund online is
  issued from the invoice view (Invoices tab → invoice → Credit Memo) —
  creating a credit memo from the order view never binds an invoice and
  keeps showing "Refund Offline" (core behavior for all gateway modules).
  Orders paid BEFORE this fix keep their invoice without `transaction_id`
  (Refund Offline only, or backfill manually).

### Added
- **Configurable checkout logo** `payment/vnpay/logo`: image upload
  (PNG/JPG/JPEG/WEBP only, SVG rejected at upload time via
  `Secomm\VNPAY\Model\System\Config\Backend\Logo`) stored in media storage
  (`media/vnpay/<scope>[/<scopeId>]/...`, survives static content deploy,
  per-website supported); overrides the bundled
  `Secomm_VNPAY::images/logo-vnpay.png`, which remains the fallback when
  empty. Mirrors the Secomm_ZaloPay checkout logo (TASK-MCHN2T). The
  renderer contract is unchanged: `window.checkoutConfig.payment.vnpay.logo`.

## 1.0.0
- Initial: VNPAY payment gateway — `Controller/Order/{Pay,Info,Ipn}`, signature/IPN validation, `payment.xml`/`config.xml` (default `active=0`).
