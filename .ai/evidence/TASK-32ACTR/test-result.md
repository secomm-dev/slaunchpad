# Evidence — TASK-32ACTR (Phase E-SL1 service-level rate aggregation, ShippingCore)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL review.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ServiceLevelRateAggregator"
OK — 14 tests, 39 assertions, 0 failure/error (5 PHPUnit deprecations pre-existing)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 297 tests, 871 assertions (0 failure/error; 5 deprecations pre-existing)
```

Test mới (`Test/Unit/Model/ServiceLevel/ServiceLevelRateAggregatorTest.php`, 14 — registry THẬT
với LEVEL_A/LEVEL_B test-defined, không Launchpad taxonomy):

- Matrix §3.3 đủ 8 hàng: [] → empty · UNAVAILABLE → 0 rates/false · TECHNICAL_FAILURE → 0/true ·
  SUCCESS → 1/false · SUCCESS+UNAVAILABLE → 1/false · **SUCCESS+TECHNICAL_FAILURE → 1/true (tín
  hiệu không suppress)** · UNAVAILABLE+TECHNICAL_FAILURE → 0/true · SUCCESS+SUCCESS → 2/false.
- Dynamic validation: unknown code (`EXPRESS` — chứng minh không hardcode) → `LocalizedException`;
  registered-but-disabled level (LEVEL_B) vẫn known (assertKnown không đọc enabled).
- Zero outcomes valid; carrier-code keys + input order preserved (30 trước 35 — không sort giá);
  reason independence (UNAVAILABLE + 'TECHNICAL_ERROR' → false; TECHNICAL_FAILURE + null → true);
  non-string key / non-outcome value → `LogicException`; reflection guard: constructor KHÔNG
  reference FallbackRateProviderInterface/Pool.
- Lỗi tự-fix trong review: draft đầu dùng named-arg spread `'key' =>` (invalid PHP) + 1 helper
  rác — rewrite bằng array trực tiếp, `php -l` + tests pass.

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 26 FAIL, 0 WARN — 0 finding TASK-32ACTR (15 baseline + 11 BUG records các
stream song song đang draft, ngoài scope).
```

## Verification greps

```text
$ grep EXPRESS|SAME_DAY|STANDARD|TECHNICAL_ERROR|SERVICE_UNAVAILABLE|FallbackRateProvider
    (E-SL1 production files) → 0 hit — không hardcoded taxonomy, không đọc reason, không fallback dependency
$ git status Ghtk/Ahamove/Mageplaza → 0 (0 carrier/Mageplaza code)
```

## Files changed (đề xuất commit — KHÔNG tự commit)

- `Api/{ServiceLevelRateAggregatorInterface, ServiceLevelRateAggregateInterface}.php` (mới)
- `Model/ServiceLevel/{ServiceLevelRateAggregator, ServiceLevelRateAggregate}.php` (mới)
- `etc/di.xml` (1 preference)
- `Test/Unit/Model/ServiceLevel/ServiceLevelRateAggregatorTest.php` (mới)
- `README.md` + `CHANGELOG.md` (0.10.0)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE
