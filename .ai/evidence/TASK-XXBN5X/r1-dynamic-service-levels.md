# Evidence — TASK-XXBN5X r1: dynamic service-level model (TL/SA review reject hardcoded constants)

Ngày: 2026-09-08 · Review round r1 trên TASK-XXBN5X đang mở (trước closure/freeze).

## Thay đổi chính

- **XÓA** `Api/ShippingServiceLevel.php` (constants class EXPRESS/SAME_DAY/STANDARD +
  `all()/exists()/assertKnown()` compile-time) + test cũ asserting fixed 3-item universe.
- **MỚI** `Api/ShippingServiceLevelInterface` (code/label/enabled/sortOrder — code = machine
  identity, label = configurable presentation) + `Model/ServiceLevel/ShippingServiceLevel` (VO
  self-guarding: empty code/label → LogicException) + `Model/ServiceLevel/ShippingServiceLevelRegistry`
  (DI `serviceLevels` array; zero-level valid; duplicate/empty code fail-fast;
  `getAll`/`getEnabled`(sortOrder stable sort)/`has`/`getByCode`/`assertKnown` — unknown code →
  `LocalizedException` = explicit configuration error).
- Docblock `CarrierServiceLevelInterface`: values = dynamic registered codes + PORTABILITY
  IMPLICATION (literal code trong reusable carrier = project declaration; long-term preferred =
  configured membership; KHÔNG sửa carrier trong r1).
- Docblock `FallbackRateProviderInterface`: bỏ constant ref → "a REGISTERED service-level machine
  code". Fallback contracts: 0 signature change.
- di.xml: thêm registry `serviceLevels` array rỗng (zero valid; không đăng ký taxonomy nào —
  Launchpad defaults thuộc composition sau này, out of scope §6).
- Storage: **Option A DI registration** (định nghĩa ít, version-controlled, chưa cần admin CRUD;
  Option B config-rows = upgrade path kèm seam đọc config lúc đó; Option C DB rejected).

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ServiceLevel|FallbackRate"
OK — 30 tests, 68 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)
  ShippingServiceLevelTest (5): transport 4 fields · defaults enabled/sortOrder 0 · disabled
    transported · empty code reject · empty label reject
  ShippingServiceLevelRegistryTest (10): zero valid · single discoverable · multiple giữ
    registration order · label độc lập code · getEnabled filter + sort sortOrder (stable, equal
    giữ thứ tự đăng ký) · assertKnown accept/reject (unknown = LocalizedException) · duplicate
    code reject · empty code reject
  FallbackRateRequestTest (6) + FallbackRateTest (5) + FallbackRateProviderPoolTest (4): giữ
    nguyên pass — fallback contracts không đổi behavior; pool test đã bỏ constant usage

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 239 tests, 724 assertions (0 failure/error; 5 deprecations pre-existing)
```

## Grep verification (r1 DoD)

```text
$ grep -rn "ShippingServiceLevel" app/code/Secomm/ShippingCore --include="*.php" | grep -v Interface
  → 0 hit ngoài Model/ServiceLevel + tests (constants class cũ đã xóa hoàn toàn)
$ grep -rn "'EXPRESS'|\"EXPRESS\"|'SAME_DAY'|'SAME_DAY'|'STANDARD'|\"STANDARD\"" Api/ Model/
  → 3 hits TẤT CẢ là docblock examples ("e.g. ...") — KHÔNG phải compile-time list; validation
    đọc registry, không đọc literal nào
$ grep -rni "mageplaza|mptablerate" ... → 0 hit (giữ nguyên từ E-SL0 ban đầu)
```

## Build + validator

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 17 FAIL, 0 WARN — 0 finding cho TASK-XXBN5X (15 baseline pre-existing +
2 từ BUG-5NR0PD/BUG-H929MC của stream song song, ngoài scope task này).
```

## Files changed (r1)

- XÓA: `Api/ShippingServiceLevel.php`, `Test/Unit/Api/ShippingServiceLevelTest.php` (+ dir rỗng)
- MỚI: `Api/ShippingServiceLevelInterface.php`, `Model/ServiceLevel/{ShippingServiceLevel,
  ShippingServiceLevelRegistry}.php`, `Test/Unit/Model/ServiceLevel/{2 test files}`
- UPDATE: `Api/CarrierServiceLevelInterface.php` + `Api/Fallback/FallbackRateProviderInterface.php`
  (docblock-only), `etc/di.xml` (registry array), `Test/Unit/Model/Fallback/FallbackRateProviderPoolTest.php`
  (bỏ constant usage), `README.md` + `CHANGELOG.md` (0.6.0 amended r1)
- Governance: SPEC r1 (§3.1-r1 + AC-1 r1 + Status), TASK record "Review rounds r1", plan r1 row,
  CURRENT_STATE sync

0 carrier / 0 Mageplaza / 0 Launchpad code thay đổi.
