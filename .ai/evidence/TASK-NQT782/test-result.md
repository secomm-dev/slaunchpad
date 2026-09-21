# Evidence — TASK-NQT782 (LT-BRIDGE-1 Launchpad_MageplazaTableRate fallback bridge)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "MageplazaTableRate|TableRate"
OK — 16 tests, 41 assertions, 0 failure/error (6 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml            # full suite
Tests: 1030, Assertions: 3643, Errors: 7
  → CẢ 7 errors = Secomm\Tracking baseline pre-existing (đã ghi trong các evidence trước).

$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.
  (Lần compile đầu FAIL: <item> trong DI array thiếu name attribute — đã fix; 8 errors
   Secomm\PromotionMaxDiscount xuất hiện kèm compile-fail cũng biến mất sau fix — chúng là
   hệ quả missing generated classes, không phải code của stream đó.)
```

Test mới (16):

- `ConfigTest` (4): mode default FALLBACK_ONLY khi unset · STANDALONE đọc từ scope · giá trị
  mode lạ → FALLBACK_ONLY · mapping/label missing → null / fallback về code.
- `FallbackRateProviderTest` (9): no-mapping → null + Mageplaza không bị đụng · STANDALONE →
  null + factory không gọi · match SUM/MIN/MAX theo cấu hình method (10+20 → 30/10/20) ·
  **zero-price → FallbackRate(0.0) hợp lệ** · no-match → null (không default price) · method
  missing → `FallbackConfigurationException` ("does not exist") · method inactive → exception
  ("inactive or out of scope" — chosen semantic §30) · request translation capture (dest
  fields + all_items=[] + cartData scalars 1.5/1250000/3.0).
- `TableRatePluginTest` (3): FALLBACK_ONLY + active=1 → warning + false + proceed never ·
  FALLBACK_ONLY + active=0 → false không log spam · STANDALONE → proceed unchanged.

## Ghi chú kỹ thuật

- **PHPUnit 10 gotcha tự-fix**: `->method()` lần 2 KHÔNG re-configure stub có sẵn → mock
  Mageplaza chuyển sang state-driven callbacks (test flip property, callback đọc).
- **DI gotcha tự-fix**: `<item>` trong array argument BẮT BUỘC `name` attribute — compile
  fail lúc đầu, fix `<item name="launchpad_mptablerate" xsi:type="object">…`.
- `Method::getCalculateRule` là magic getter (docblock @method) → mock qua `addMethods()`.

## Verification

```text
$ git status app/code/Mageplaza vendor/ → 0 (0 vendor/Mageplaza edit — §34)
$ grep RateRequest trong ShippingCore Fallback contracts → vẫn 0 (fallback request vẫn là
  ShippingCore DTO; Magento RateRequest chỉ sinh trong bridge, không leak ngược)
$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 26 FAIL, 0 WARN — 0 finding TASK-NQT782 (15 baseline + 11 BUG records stream
song song).
$ php bin/magento module:enable Launchpad_MageplazaTableRate → config.php:1 entry added
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `app/code/Launchpad/MageplazaTableRate/**` (module mới: registration, module.xml, di.xml,
  config.xml, adminhtml/system.xml, Config, Source/Mode, FallbackRateProvider,
  Exception/FallbackConfigurationException, Plugin/Carrier/TableRate, README, CHANGELOG,
  3 test files)
- `app/etc/config.php` (enable entry — qua module:enable)
- `dev/tests/unit/phpunit-secomm.xml` (thêm testsuite directory Launchpad/*)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE

0 Secomm_ShippingCore / 0 Mageplaza / 0 carrier code thay đổi trong task này.
