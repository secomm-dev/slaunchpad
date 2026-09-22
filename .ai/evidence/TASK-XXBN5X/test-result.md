# Evidence — TASK-XXBN5X (Phase E-SL0 service-level + fallback contracts, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingServiceLevel|FallbackRate"
OK — 20 tests, 52 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 229 tests, 708 assertions (0 failure/error; 5 deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml            # full Secomm suite
Tests: 918, Assertions: 3371, Errors: 7
  → CẢ 7 errors nằm ở Secomm\Tracking\Test (ArgumentCountError vendor signature drift — cùng
    họ pre-existing được ghi trong evidence TASK-5XDG1P; module không liên quan, 0 regression).
```

Test mới: `Test/Unit/Api/ShippingServiceLevelTest` (5 — identities ổn định, exists matrix,
assertKnown reject, carrier-declaration stub shape) · `Test/Unit/Model/Fallback/FallbackRateRequestTest`
(6 — transport 8 trường, nullable defaults, zero dims hợp lệ, negative weight/subtotal/qty
reject) · `FallbackRateTest` (5 — amount/label/estimate, zero+negative amount reject, label rỗng
reject) · `FallbackRateProviderPoolTest` (4 — zero valid, order preserved, invalid entry reject,
provider stub match→rate/no-match→null).

## AC-6 grep (forbidden references — sau khi genericize docblock)

```text
$ grep -rni "mageplaza|mptablerate" app/code/Secomm/ShippingCore --include="*.php" --include="*.xml"   → 0 hit
$ grep -rn "use Magento\\Quote" app/code/Secomm/ShippingCore/{Api/Fallback,Model/Fallback,Api/ShippingServiceLevel.php,Api/CarrierServiceLevelInterface.php} → 0 hit
   (0 raw RateRequest qua fallback surface; substring FallbackRateRequest* không tính)
$ grep -rn "Launchpad" Api/Fallback Model/Fallback → 0 hit (docblock wording trung tính
   "optional third-party bridge module"; ghi chú future-bridge thuộc SPEC/SPIKE, không nằm trong code)
```

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 17 FAIL, 0 WARN — 15 baseline pre-existing + 2 FAIL MỚI KHÔNG THUỘC task này:
  BUG-5NR0PD (display title stale) + BUG-H929MC (Mini-Spec missing Expected Behavior) — records
  do stream song song tạo trong lúc chạy (đang draft, ngoài scope TASK-XXBN5X).
  Records TASK-XXBN5X (SPEC + plan + task record + FEAT ticket_ref): 0 FAIL / 0 WARN.
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `ShippingCore/Api/ShippingServiceLevel.php`, `Api/CarrierServiceLevelInterface.php` (mới)
- `ShippingCore/Api/Fallback/{FallbackRateRequestInterface, FallbackRateInterface, FallbackRateProviderInterface}.php` (mới)
- `ShippingCore/Model/Fallback/{FallbackRateRequest, FallbackRate, FallbackRateProviderPool}.php` (mới)
- `ShippingCore/etc/di.xml` (pool array argument)
- `ShippingCore/Test/Unit/Api/ShippingServiceLevelTest.php` + `Test/Unit/Model/Fallback/{3 tests}.php` (mới)
- `ShippingCore/{README.md, CHANGELOG.md}` (0.6.0)
- `.ai/{specs/SPEC-TASK-XXBN5X-…, plans/TASK-XXBN5X-…, records/tasks/TASK-XXBN5X.md,
  records/features/FEAT-YA2C0W.md, project/CURRENT_STATE.md}` (governance + memory)

0 carrier / 0 Mageplaza / 0 Launchpad code thay đổi.
