---
id: EVIDENCE-TASK-CG6BM7-VALIDATION
work_item: TASK-CG6BM7
spec: SPEC-TASK-CG6BM7-zalopay-postfix-audit
kind: l3-validation
created: 2026-09-16
environment: container slaunchpad-phpfpm-1 (PHP 8.3.20, PHPUnit 10.5.64, PHPCS w/ Magento2 standard), harness /tmp/zlp-audit (isolated; container-only autoloader shim)
---

# §12 L3 validation — kết quả trung thực

## PHP syntax (php -l)
- PASS — toàn bộ 12 file sửa + 4 test file mới: `php -l` sạch (chạy trong container PHP 8.3.20).

## Unit — ZaloPay (toàn bộ `Test/Unit`)
- PASS — `phpunit -c phpunit-zlp-harness.xml app/code/Secomm/ZaloPay/Test/Unit`
  **OK — 223 tests, 825 assertions** (bao gồm 34/34 test của ma trận §13:
  EMAIL 1–7, RATE 8–15, REFUND CMD 16–23, REFUND CRON 24–30, RESPONSE 31–34).
  4 suite mới: RateTest 5/43, OrderFinalizerTest 21/83, ResponseMessagesHandlerTest 5/6,
  RefundCommandTest 7/30, RefundCronjobTest 8/38.

## PHPCS (Magento2 standard, module-wide)
- ERRORS: **0** (exit 0 với `-n`).
- WARNINGS: 284 module-wide — đa số annotation-style pre-existing trên cả baseline.
- So sánh per-file baseline `a48de3c` vs worktree cho 6 file source đã sửa:
  +2 MethodAnnotationStructure (RefundCronjob), +1 (ResponseMessagesHandler), +1 (OrderFinalizer),
  +2 VariableTranslation (RefundCommand — message động từ provider map, cố ý),
  −1 LineLength (RefundCommand). Tổng +5 warnings style, 0 errors mới → xem findings.md M6.

## setup:di:compile
- **PARTIAL (sandbox environment artifact — KHÔNG claim PASS)**:
  - Sandbox `/tmp/di-check` (BP riêng, `generated/` là dir thật, vendor symlink read-only;
    share workspace **không bị ghi** — xác minh `find ... -newermt` = 0 file/dir mới).
  - Root cause block: Magento `CompilerPreparation::handleCompilerEnvironment()` cố ý
    DELETE `generated/code` khi `setup:di:compile` chạy (hành vi chuẩn); trong sandbox,
    generation-on-the-fly không ghi file (artifact của bản copy sandbox) → các step sau scan
    path không tồn tại.
  - Kết quả thật: **Proxies ✓, Repositories ✓, Service data attributes ✓, Application code
    generator ✓ (3/9→4/9 — đã `class_exists()` toàn bộ app/code bao gồm ZaloPay worktree),
    Interceptors ✓ (4/9→5/9)**; dừng ở **Area configuration aggregation (5/9)** với lỗi
    `Preference "Magento\Framework\View\DesignInterface" → Magento\Theme\Model\View\Design\Proxy`
    — lỗi **Magento core preference, không liên quan code ZaloPay**.
- Bằng chứng bù (compile-equivalent, targeted):
  - Class-load: 73 file PHP, **72/72 symbol load OK** với Magento framework thật + mọi
    constructor param type resolve (script `/tmp/zlp-classload-check.php`).
  - di.xml wiring: 58 class references resolve; 17 còn lại là tên virtualType tự tham chiếu
    (hợp lệ); mọi plugin `type` tồn tại.

## Regression — toàn bộ `app/code/Secomm/*/Test/Unit` (23 module)
- 1047 tests: 8 errors + 2 failures — TẤT CẢ ở `Secomm\FulfillmentCore` / `Secomm\Tracking`.
- Chứng minh pre-existing: chạy lại 2 suite đó với **ZaloPay module baseline `a48de3c`**
  → y hệt 8 errors + 2 failures. Thay đổi ZaloPay không gây ra lỗi nào.
- ZaloPay suite sau khi khôi phục worktree: OK 223/825 (đã verify lại).

## Integration runtime
- **INTEGRATION = ENVIRONMENT_BLOCKED** (không claim PASS).
- Lý do: DB `magento_integration_tests` ĐÃ TỒN TẠI trên MySQL dev (`db` reachable, root/magento).
  Chạy integration suite sẽ DROP/đè DB dev chung và build sandbox ghi vào share `dev/` —
  vi phạm ràng buộc SHARED_WORKSPACE_MODIFIED=NO. Không chạy chủ động.

## §9 SMTP/staging (READ-ONLY)
- KHÔNG đụng bất kỳ env/system config/credentials/Mailpit/Mageplaza SMTP nào.
- `ENVIRONMENT_MAIL_CONFIG_BLOCKED/MISCONFIGURED`: không có evidence mail config trong
  phạm vi audit này (email flow chỉ phụ thuộc `OrderSender` Magento-native; khuyến nghị
  `sales_email/general/async_sending` cho retry-native — xem DEC-TASKCG6BM7-002).
