# Changelog

## [Unreleased] - 2024-07-30

### Added
- Mageplaza OSC layout adapter: onestepcheckout_index_index.xml reuses the existing
  zalopay.js renderer (compliant with .spec §15 — billing-step declares uiComponent)
- Plugin-based architecture to replace global preferences:
  - TotalMinMaxPlugin - ZaloPay currency conversion in min/max order validation
  - CreditmemoServicePlugin - Refund-specific processing
  - SuccessValidatorPlugin - 15-minute grace period for success page validation
  - CreditmemoPlugin - PROCESSING state (value 4) for credit memo states
- Return/IPN validation separation:
  - ReturnValidator - GET params callback validation
  - CompleteValidator - POST JSON IPN validation
  - IpnCommand - IPN command handler
  - CompleteUpdateDetailsCommand - Return command handler
  - IpnUpdateDetailsCommand - IPN update handler

### Changed
- Controllers `Start`, `Ipn` and `ReturnAction` migrated to composition
  (`implements HttpGetActionInterface` / `HttpPostActionInterface` +
  `CsrfAwareActionInterface`, injected dependencies instead of
  `extends \Magento\Framework\App\Action\Action` — removed the deprecated
  base-class inheritance flagged by static analysis (PHP6406). Behaviour
  unchanged: same routes, same CSRF semantics, legacy fall-through of
  `Start::executeLegacy()` (null when no order) and `Ipn` non-POST `null`
  result preserved.
- PHP 8.1-8.4 support via composer.json constraint update
- Migrated HTTP client from ZendClient to LaminasClient in Gateway/Http/Client/Zend.php
- Updated Gateway/Helper/Authorization.php:
  - Added EncryptorInterface injection for key1/key2 decryption
  - Implemented getKey1() and getKey2() with automatic decryption
- Removed deprecated ObjectManager::get() usage in Controller/Payment/Start.php
- Cleaned up unused constructor parameters in Controller/Payment/Ipn.php
- Removed dead code: writeLog() method from Ipn.php
- Fixed RefundCommand.php:
  - Replaced deprecated Creditmemo::STATE_PROCESSING with CreditmemoPlugin::STATE_PROCESSING
  - Updated namespace import for new plugin class
- Removed global preferences (6 total):
  - Magento/Sales/Model/Service/CreditmemoService → plugin
  - Magento/Sales/Model/Order/Creditmemo → plugin
  - Magento/Payment/Model/Checks/TotalMinMax → plugin
  - Magento/Checkout/Model/Session/SuccessValidator → plugin
  - Secomm/ZaloPay/Api/Data/RefundInfoInterface → removed (unused)
  - Secomm/ZaloPay/Api/Data/RefundTransactionInterface → removed (unused)

### Fixed
- Return flow crashed with "Undefined array key trans_data" in TransactionCompleteHandler
  because the Return redirect callback (GET: amount, appid, apptransid, bankcode,
  checksum, status...) carries NO trans_data and no zp_trans_id — only the IPN
  payload does. Split the handler: new TransactionReturnHandler (null-safe, persists
  apptransid as additional info, does NOT register a transaction since Return is
  non-authoritative) wired into CompleteUpdateDetailsCommand; TransactionCompleteHandler
  (registers zp_trans_id) kept for IpnUpdateDetailsCommand. Captured via added
  ReturnAction error logging.
- Ipn controller: declared missing $logger property (dynamic property deprecated in PHP 8.3).
- ReturnValidator (R9) was copy of CompleteValidator and crashed on Return flow:
  - TypeError: validateTotalAmount declared `array|string` but received float —
    fixed to `float`.
  - Inherited parent::validateTransactionId() checks `trans_data[zp_trans_id]`
    which only the IPN payload has — overrode to verify the app transaction id
    (apptransid) that the Return redirect actually carries.
  - Removed the incorrect MAC validation (Return uses different field names
    than the request, and Return is non-authoritative — IPN validates MAC).
- HTTP client migration completeness: replaced Zend-only API calls removed in
  LaminasClient — setConfig() → setOptions() and request() → send() (matches
  core Magento\Payment\Gateway\Http\Client\Zend on M2.4.8). Without this the
  GetPayUrl/Refund/RefundQuery requests crashed at runtime.
- Restored required <model>ZaloPayFacade</model> and <is_gateway>1</is_gateway>
  in config.xml — Adapter::isAvailable() requires is_gateway or the method never
  appears in checkout (per .spec payment-gateway.md §10)
- TotalMinMaxPlugin no longer throws on missing USD→VND currency rate: skips
  conversion when min/max order totals are empty and falls back gracefully so it
  does not hide the method from checkout
- Regenerated db_schema_whitelist.json to match db_schema.xml
- Added declare(strict_types=1) to all new PHP files per .spec/constitution.md §1
- PHP 8.3 compatibility verified via setup:di:compile

### Removed
- Duplicate Secomm\ZaloPay\Model\Ui\ConfigProvider + Gateway\Config\Config — the
  module already ships ZaloPayConfigProvider (registered in etc/frontend/di.xml)
- Duplicate CompositeConfigProvider registration from etc/di.xml (global) —
  per .spec §12 it must live only in etc/frontend/di.xml

### Security
- Removed hardcoded sandbox credentials from default configuration
- Implemented automatic key1/key2 decryption for encrypted backend_model

## [Unreleased] - Previous

### Fixed
- Fixed a bug that prevented redirection to Zalopay.
- Resolved an issue where refunds were not being processed correctly.
- Addressed a bug that sometimes caused the Zalo payment method not to be displayed.
- Change UX when an order is successfully placed using Zalopay on a mobile device

### Added
- Added additional status "processing" for better order tracking.
- Implemented a cron job to periodically check the refund status.
- Added configuration description for Zalo Pay.
- Introduced additional forms for the Zalo Pay payment method.
- Delete refund items that have been processed
- Validate Minimum order total and maximum order total
- Translate the module into English and Vietnamese.
- Handle errors code from Zalopay

### Improved
- Updated the IPN (Instant Payment Notification) functionality for better reliability.

### Updated
- Upgraded Zalopay version from v1 to v2.
- Restructured the API to enhance performance and security.
- Adjusted API parameters to align with the new Zalopay structure.
- Updated codebase to PHP version 8.1 for compatibility and optimization.

### Testing
- Conducted unit tests for controllers to ensure robust functionality.
