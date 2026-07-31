# Changelog

## [2.0.0] - 2024-07-30

### Added
- Full rebuild of Secomm_MoMo on **MoMo API v2** (redirect + IPN + refund),
  replacing the deprecated 2019 API 1.0 (RSA) implementation.
- `Magento\Payment\Model\Method\Adapter` facade (`MoMoFacade`) with Gateway
  CommandPool — replaces the deprecated `AbstractMethod` god-class.
- HMAC-SHA256 signature helper over the MoMo v2 rawSignature.
- Encrypted backend_model for `access_key` and `secret_key` (admin config).
- Return (GET, lenient) + Notify (IPN, authoritative) separation.
- ACL resource, composer.json, README, strict_types + docblocks on all files.

### Removed
- Entire 2019 codebase: `Model/MoMo.php` god-class (`AbstractMethod`), raw curl
  `HttpClient`, phpseclib v1 RSA encoder, `Controller/Transation/RefundOrder.php`
  scratch code (hardcoded order id + ObjectManager), MoMo API 1.0 endpoints.

### Fixed
- `module.xml`: `<sequence>` now nested inside `<module>` (was a sibling — invalid schema).
- `serect_key` typo → `secret_key` across system.xml / config keys / methods.
- Duplicate `CompositeConfigProvider` registration (now only in `etc/frontend/di.xml`).
- `config.xml`: default `active=0`, added required `is_gateway`/`can_*` flags.
