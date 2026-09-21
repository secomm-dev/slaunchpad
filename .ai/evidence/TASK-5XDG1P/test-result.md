# Evidence — TASK-5XDG1P (Phase E-B local canonical orchestration, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingAddressResolution"
OK (but pre-existing deprecations) — 24 tests, 94 assertions, 0 failure/error

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 208 tests, 654 assertions (0 failure/error; 5 PHPUnit deprecations pre-existing,
                                    đúng bộ 5 đã có ở E-A baseline ExternalAddressResolverPoolTest)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml            # full Secomm suite
Tests: 866, Assertions: 3240, Errors: 7
  → CẢ 7 errors nằm ở Secomm\Tracking\Test\Unit\Event\EventNormalizerTest
    (ArgumentCountError: Magento\Sales\Model\Order\Item/Order::__construct() — vendor signature
    drift của test cũ, module KHÔNG liên quan, 0 reference tới ShippingCore). E-A baseline sáng
    cùng ngày ghi cùng họ lỗi (15 errors @ HEAD); một phần đã được fix bởi stream khác trong ngày.
    Không phải regression của TASK-5XDG1P (task chỉ thêm file vào Secomm_ShippingCore + .ai records).
```

Test mới (`Secomm_ShippingCore/Test/Unit/Model/Address/`):

- `ShippingAddressResolutionManagerTest` (19 — manager + MOCK resolver): EXACT/MAPPED/AMBIGUOUS/
  UNMAPPED passthrough · AMBIGUOUS không auto-select (unitCode null + `assertNotSame` từng
  candidate) · cache hit (resolver 1 lần + `assertSame` instance) · cache separation (đổi unit /
  đổi targetScheme) · cache AMBIGUOUS + UNMAPPED (không re-call) · missing sourceScheme /
  sourceUnitCode → UNMAPPED, resolver 0 lần, có cache · non-VN (US + lowercase `us`) →
  `UnsupportedDestinationException`, resolver 0 lần, lặp vẫn throw (không cache) · countryId null
  → KHÔNG bypass (đi tiếp resolution) · unknown scheme → `LocalizedException` propagate ·
  capability stub `supportsTextualFallback()` ném `LogicException` nếu bị consult (guard directive §12).
- `ShippingAddressResolutionManagerIntegrationTest` (5 — manager + `VnAdminAddressResolver` THẬT,
  chỉ mock reference layer): same-scheme EXACT (không cần mapping edge) · cross-scheme MAPPED ·
  reverse-merge AMBIGUOUS (candidates sorted giữ nguyên vào shipping result) · cross-scheme UNMAPPED
  · same-scheme unknown unit → UNMAPPED (không throw).

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 15 FAIL, 0 WARN — ĐÚNG baseline pre-existing được ghi trong CURRENT_STATE
(E-A, cùng ngày): stale H1 renders (TASK-9394A9/ADT94K/F9XJ5G/J9AVGK/NDSZ7V/Q4B98P/SPIKE-W273TB),
plan/spec cũ (PLAN-TASKN35E28, SPEC-FEAT-J06WXZ/ZKD4VA/SPEC-TASK-N1VBSM, plan-abandonedcart,
plan-TASK-N1VBSM, TASK-6X2FQH, BUG-EFWXPA). 0 FAIL/WARN mới từ records TASK-5XDG1P
(SPEC + plan + task record + FEAT-YA2C0W ticket_ref đều pass --check-specs/--check-identity).
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `app/code/Secomm/ShippingCore/Model/Address/ShippingAddressResolutionManager.php` (mới)
- `app/code/Secomm/ShippingCore/Model/Address/Exception/UnsupportedDestinationException.php` (mới)
- `app/code/Secomm/ShippingCore/Api/Address/ShippingAddressResolutionManagerInterface.php` (docblock-only)
- `app/code/Secomm/ShippingCore/etc/di.xml` (1 preference + comment)
- `app/code/Secomm/ShippingCore/Test/Unit/Model/Address/{ShippingAddressResolutionManagerTest,ShippingAddressResolutionManagerIntegrationTest}.php` (mới)
- `app/code/Secomm/ShippingCore/{README.md,CHANGELOG.md}` (E-B surface)
- `.ai/{specs/SPEC-TASK-5XDG1P-…md, plans/TASK-5XDG1P-…md, records/tasks/TASK-5XDG1P.md,
  records/features/FEAT-YA2C0W.md, project/CURRENT_STATE.md}` (governance + memory)
