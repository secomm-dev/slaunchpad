# Changelog

## [2.0.1] - 2026-08-27

### Fixed
- Return success no longer depends on the checkout session still holding its
  `Last*` keys (duplicate return / lost cookies used to fail the core
  SuccessValidator and send a paid customer to the cart page). New
  `Service/ReturnProcessor`: on `resultCode = 0` the order is resolved from
  MoMo's own `orderId` parameter (= increment id; session fallback), verified
  to be a MoMo order, and the five success-session keys are rebuilt exactly
  like core `Onepage::saveOrder` (`clearHelperData` first).
- `Controller/Payment/ReturnAction` now delegates to `ReturnProcessor`; a
  non-MoMo or unresolvable order surfaces a customer-safe error + cart
  redirect instead of a hollow success page. Non-zero `resultCode` keeps the
  historical lenient cart behaviour (no lookup, no session writes).

### Added
- Unit tests: `Test/Unit/Service/ReturnProcessorTest.php`,
  `Test/Unit/Service/SessionStub.php`,
  `Test/Unit/Controller/Payment/ReturnActionTest.php` (9 tests — suite now
  18 tests / 36 assertions, all passing).

### Changed
- Controllers `Redirect`, `Notify` and `ReturnAction` migrated to composition
  (`implements HttpGetActionInterface` / `HttpPostActionInterface` +
  `CsrfAwareActionInterface`, injected dependencies instead of
  `extends \Magento\Framework\App\Action\Action` — removed the deprecated
  base-class inheritance flagged by static analysis (PHP6406). Behaviour
  unchanged: same routes, same CSRF semantics, same results.

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
