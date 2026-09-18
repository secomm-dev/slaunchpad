# PLAN-TASK-MCHN2T — ZLP-OPS-01 operator diagnostics + debug mode + checkout branding

- **Work item**: TASK-MCHN2T (external: ZLP-OPS-01, GitHub issue #8)
- **Mode**: B | **Risk**: medium | **Branch**: `thanhle74/zalopay-zlp-ops-01-add-operator-diagnostics-debu` | **BASE_SHA**: `87f4db44`

| Specification | Embedded Mini-Spec (TASK-MCHN2T — behavioral contract từ GitHub issue #8 / ZLP-OPS-01) |
|---|---|

## Approach (tổng quan)

Feature B tận dụng gate + masking đệ quy sẵn có của core `Magento\Payment\Model\Method\Logger` (đọc `payment/zalopay/debug` qua virtual `ZaloPayConfig`): chỉ thêm config field/default + wire inner logger của `ZaloPayLogger` sang Monolog logger ghi `var/log/zalo-pay.log` (DEBUG handler), fix mask thiếu `key1` trong `Zend.php` + truyền maskKeys để core mask đệ quy cả response. Feature A: console command read-only reuse `query_transaction` pool + extract `RefundCronjob::buildQuerySubject()` thành `Gateway\Helper\RefundQuerySubjectBuilder` dùng chung. Feature C: pattern `Secomm/VietQr` (image field + `Config\Backend\Image` subclass + media URL ở ConfigProvider).

## Steps

1. **Debug mode (B)**: `system.xml` field `debug` (select Yesno, sortOrder 61, website scope) + `config.xml` `<debug>0</debug>` + `Logger/DebugHandler.php` (extends Handler, `Monolog\Logger::DEBUG`, cùng file `zalo-pay.log`) + `di.xml`: `ZaloPayLogger` thêm argument `logger` → `ZaloPayHttpDebugLogger` (Secomm Monolog Logger + handler ZaloPayDebugHandler) + `Zend.php`: thêm `key1` vào sensitive list, truyền maskKeys vào `logger->debug($log, $maskKeys)` (core mask đệ quy response).
2. **Refund subject builder (A-1)**: `Gateway/Helper/RefundQuerySubjectBuilder.php` — body chính xác từ `RefundCronjob::buildQuerySubject()` (unserialize `additional_information` → validate `m_refund_id` → timestamp mới → unset MAC cũ → `Authorization::getMac`); `RefundCronjob` inject + delegate, xóa private method; cập nhật `RefundCronjobTest` wiring.
3. **Diagnose command (A-2)**: `Console/Command/DiagnoseCommand.php` — name `zalopay:diagnose`, options `--json`, `--query-payment`, `--query-refund`; report mode/active/debug/credentials-presence/URL paths; exit `Cli::RETURN_FAILURE` khi config invalid/incomplete hoặc query fail; query-payment qua `ZaloPayCommandPool::get('query_transaction')`; query-refund qua `RefundCollection` (filter `m_refund_id`) + subject builder + `RefundQueryCommand::setZaloRefundId()->getRefundQuery()` — không đụng state/budget row; mutual exclusion 2 options.
4. **CLI registration**: `di.xml` `CommandListInterface` + type block cho command (config=ZaloPayConfig, commandPool=ZaloPayCommandPool, refundQueryCommand, subjectBuilder, refundCollectionFactory, scopeConfig).
5. **Logo (C)**: `system.xml` field `logo` (image, sortOrder 4, upload_dir/base_url `zalopay` scope_info=1, backend `Secomm\ZaloPay\Model\System\Config\Backend\Logo` — `_getAllowedExtensions = ['png','jpg','jpeg','webp']`) + `ZaloPayConfigProvider`: inject `ScopeConfigInterface` + `StoreManagerInterface`, `logoSrc` = custom ? `media base + 'zalopay/' + stored` : default asset; dọn deps không dùng (`PaymentHelper`, `ResolverInterface`).
6. **Tests (Test/Unit)**: `DiagnoseCommandTest`, `RefundQuerySubjectBuilderTest`, `ZendTest` (OFF: inner logger never called; ON: masked `****` cho mac/key1/key2, response nested mask), `LogoTest`, `ZaloPayConfigProviderTest`; cập nhật `RefundCronjobTest`.
7. **Docs**: README (CLI usage/exit codes, Debug Mode, Logo) + CHANGELOG entry.
8. **Validate**: CodeGraph init/index trước mutate; `php -l`; module unit suite (docker `m2r-php`); full suite baseline 7 pre-existing; PHPCS severity 10; `setup:di:compile`; `--check-specs`; evidence `.ai/evidence/TASK-MCHN2T/`.

## Rủi ro & biện pháp

- **RefundCronjob refactor**: extract service behavior-preserving; cron tests cập nhật wiring; không đổi budget/state handling.
- **Mask `key1` fix**: chỉ ảnh hưởng payload log (debug), không ảnh hưởng request thật.
- **`ZaloPayLogger` logger argument**: hiện auto-wire global LoggerInterface (system.log) nhưng debug OFF nên không có payload nào đang ghi — reroute sang zalo-pay.log không mất log hiện hữu.
- **Logo scope**: website-scope giống credentials; stored value `websites/1/x.png` → URL media đúng theo `_prependScopeInfo` (đã verify vendor).

## Progress

- [x] Step 1 — Debug mode (B): system.xml `debug` field (sortOrder 64) + config.xml `<debug>0</debug>` + `Logger/DebugHandler.php` + di.xml `ZaloPayHttpDebugLogger`/`ZaloPayDebugHandler` + `ZaloPayLogger` reroute → `var/log/zalo-pay.log`; `Zend.php` thêm `key1` + truyền maskKeys (mask đệ quy response).
- [x] Step 2 — Refund subject builder (A-1): `Gateway/Helper/RefundQuerySubjectBuilder.php` + `RefundCronjob` delegate (constructor slimmed: removed Json/Authorization); `RefundCronjobTest` re-wired với boundary mock.
- [x] Step 3 — Diagnose command (A-2): `Console/Command/DiagnoseCommand.php` (`zalopay:diagnose`, `--json`, `--query-payment`, `--query-refund`, mutual exclusion, whitelist projection, exit `Cli::RETURN_*`).
- [x] Step 4 — CLI registration: di.xml `CommandListInterface` + type block (config/commandPool/refundQueryCommand; còn lại autowire).
- [x] Step 5 — Logo (C): backend `Model/System/Config/Backend/Logo.php` (png/jpg/jpeg/webp) + system.xml `logo` field + `ZaloPayConfigProvider` (ScopeConfig + StoreManager, media URL website→default scope, fallback asset, dọn `PaymentHelper`/`ResolverInterface`).
- [x] Step 6 — Tests: 5 class mới (DiagnoseCommandTest 12, RefundQuerySubjectBuilderTest 3, ZendTest 3, LogoTest 1, ZaloPayConfigProviderTest 4) + RefundCronjobTest — suite 390/1397 green.
- [x] Step 7 — Docs: README (Operator toolkit: CLI/exit codes, Debug Mode, Logo) + CHANGELOG `[Unreleased]`.
- [x] Step 8 — Validation: php -l + XML parse OK; module suite green; full suite 16E+2F ĐỀU ở module khác (baseline "7 Tracking" stale — diff gói trong ZaloPay); PHPCS 0 errors; `setup:di:compile` OK; CLI smoke PASS; validators 0 FAIL/WARN cho TASK-MCHN2T; evidence `.ai/evidence/TASK-MCHN2T/`. Record → `in_review` (chờ TL review; không commit/push).
- [x] Correction round 1 (coordinator CORRECTION_REQUIRED): (1) report return endpoint → canonical `zalopay/payment/returnaction` + assertion test; (2) `app_user` vào required set (text + JSON assertions); (3) mask `app_user` trong `Zend::sensitiveKeys()` + regression test PII. Re-validate: suite 391/1407 green, PHPCS 0, compile OK, smoke xác nhận 3 fix. Commit + non-force push cùng branch.
