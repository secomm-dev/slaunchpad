# Evidence — TASK-AQT7V3 (Phase E-A contracts, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 188 tests, 577 assertions (0 failure/error; 5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml            # full Secomm suite
Tests: 837, Assertions: 3104, Errors: 15  ← 15 errors GIỐNG HỆT ở HEAD không có changes
$ git stash -u && php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml
Tests: 818, Assertions: 3048, Errors: 15  → pre-existing (Secomm_Tracking EventNormalizerTest
vàO Order/Item ArgumentCountError — môi trường/test cũ, không liên quan); +19 tests của task
đều PASS, 0 regression.
```

Test mới (`Secomm_ShippingCore/Test/Unit/Model/Address/`): ResolvedShippingAddressTest (12 —
4 status · candidate order preservation · AMBIGUOUS không lộ unitCode · UNMAPPED không unitCode ·
invariant rejections · isResolved matrix), ShippingAddressResolutionContextTest (4),
ExternalAddressResolverPoolTest (3 — empty pool valid, DI order, contract rejection).

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ bash .ai/bin/project-ai-validate --check-records --check-specs --check-identity
→ 0 finding trên artifact TASK-AQT7V3 (spec/plan/record pass --check-specs + title render OK).
→ 15 FAIL còn lại — TOÀN BỘ pre-existing (stale display title TASK-9394A9/ADT94K/F9XJ5G/
  J9AVGK/NDSZ7V/Q4B98P/SPIKE-W273TB; PLAN-TASKN35E28; TASK-6X2FQH decisions empty;
  SPEC-FEAT-J06WXZ/SPEC-FEAT-ZKD4VA/SPEC-TASK-N1VBSM naming; 2 plan cũ thiếu Specification
  reference; BUG-EFWXPA thiếu Out of Scope). Backlog cũ, ngoài scope task này.
```

## Scope confirmation (diff summary)

Files changed (git status):

- `app/code/Secomm/ShippingCore/etc/module.xml` — sequence += `Secomm_VietNamAddress` (DEC-004 D1)
- `app/code/Secomm/ShippingCore/etc/di.xml` — 2 preference VO + pool array argument (D7 pattern)
- `app/code/Secomm/ShippingCore/Api/Address/*` — 5 interfaces (mới)
- `app/code/Secomm/ShippingCore/Model/Address/*` — 3 concrete (mới)
- `app/code/Secomm/ShippingCore/Test/Unit/Model/Address/*` — 3 test classes (mới)
- `app/code/Secomm/ShippingCore/{README,CHANGELOG}.md` — 0.4.0
- `.ai/specs/SPEC-TASK-AQT7V3-…md`, `.ai/plans/TASK-AQT7V3-…md`, `.ai/records/tasks/TASK-AQT7V3.md`,
  `.ai/records/features/FEAT-YA2C0W.md` (ticket_ref += TASK-AQT7V3 + TASK-Q4B98P)

**KHÔNG đụng**: Secomm_GiaoHangNhanh, Secomm_GhnAddressMapper, Secomm_Ghtk, Secomm_Ahamove,
Secomm_VietNamAddress, db_schema, config, i18n. (`.ai/tickets/TASK-ND6AZ2-…md` modified từ
TRƯỚC session — không phải của task này.)

## GHN hardcoded fallback — re-verified còn live (report-only)

- `GiaoHangNhanh/etc/config.xml:26` — `<is_develop_mode>1</is_develop_mode>` (fallback BẬT mặc định)
- `AbstractDataBuilder.php:161-162 + 166-167` — miss mapping/thiếu data → `toDistrictId=1456, toWardCode='21511'`
- `ServicesDataBuilder.php:21` — `$toDistrict = 1456`
- `SynchronizeOrderDataBuilder.php:90` — `$fromWardName = 'Phường 17'`

→ Khuyến nghị BUG task độc lập: "Remove/disable GHN hardcoded destination fallback — fail closed
khi mapping unavailable" (khớp DEC-FEATYA2C0W-004 Consequences: default 0 + bỏ fallback).
