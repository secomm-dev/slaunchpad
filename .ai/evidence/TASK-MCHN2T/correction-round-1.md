# TASK-MCHN2T — Correction round 1 evidence (coordinator CORRECTION_REQUIRED)

Date: 2026-09-18 · Same issue #8 / same branch / same workspace / no state-machine change.

## Findings → fixes

1. **Wrong return endpoint** → `DiagnoseCommand::buildReport()` now reports the
   canonical route `zalopay/payment/returnaction` (source of truth:
   `Gateway/Request/OrderAdditionalInformationDataBuilder.php:117`,
   `Controller/Payment/ReturnAction.php`). Regression guard:
   `assertStringContainsString('zalopay/payment/returnaction', ...)`
   + `assertDoesNotMatchRegularExpression('#zalopay/payment/return(?!action)#')`.
2. **`app_user` missing from required set** → added to `$required`
   (ZaloPay v2 create contract; `ZaloAppInfoDataBuilder` always emits it).
   New test `testMissingAppUserExitsNonZeroAndNamesIt` covers text output
   AND the `--json` projection (`missing_required_credentials`).
3. **`app_user` PII in debug log** → added to `Zend::sensitiveKeys()` so it is
   pre-masked ('****') in the request body AND included in the recursive
   maskKeys handed to `Magento\Payment\Model\Method\Logger` (covers nested
   response echoes). Regression proof: `testDebugModeOnWritesMaskedRecord`
   asserts the raw value `RAW-PII-USER` never appears in the Monolog output.

Not broadened: the known `appuser`/`app_user` merchant_info mismatch stays
out of scope per coordinator instruction.

## Re-validation (exact final tree, container m2r-php, PHP 8.3.20)

- php -l: DiagnoseCommand.php, Zend.php, DiagnoseCommandTest.php, ZendTest.php → all clean.
- Focused tests (`--filter "DiagnoseCommandTest|ZendTest"`): 16 tests / 73 assertions OK.
- ZaloPay suite: **391 tests / 1407 assertions, 0 failures, 0 errors**
  (5 PHPUnit deprecations pre-existing in other modules).
- PHPCS Magento2 severity 10 whole module: **0 errors** (1 pre-existing warning).
- `setup:di:compile`: success.
- CLI smoke (unconfigured store): `return: .../zalopay/payment/returnaction`,
  `app_user: missing` listed, "missing: app_id, key1, key2, app_user", exit 1.
- `--check-specs` / `--check-identity`: 0 mentions of TASK-MCHN2T.
