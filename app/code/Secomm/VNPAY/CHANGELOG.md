# Secomm_VNPAY — Changelog

## [Unreleased] — Configurable checkout logo

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
